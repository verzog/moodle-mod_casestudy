<?php
// Copyright (c) Skin Cancer College Australasia.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Skin Cancer
// College Australasia for use with Moodle. It is NOT free software and is
// NOT released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Skin Cancer College Australasia. The software
// is provided "as is", without warranty of any kind, express or implied.

/**
 * Image optimisation for case study file uploads.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Downscale and re-encode large uploaded images so storage and backups stay small.
 *
 * Images are only touched when they are larger than the configured maximum edge (or carry an
 * EXIF rotation that needs baking in), so an already-optimised image is left byte-for-byte alone
 * on subsequent passes. This deliberately avoids repeated re-compression ("generation loss"):
 * once an image is within bounds and its orientation is normalised, every later run is a no-op.
 */
class image_optimizer {

    /** @var int Default longest-edge cap in pixels when no admin setting is present. */
    const DEFAULT_MAX_EDGE = 2560;

    /** @var int Default JPEG quality (0-100) when no admin setting is present. */
    const DEFAULT_QUALITY = 85;

    /**
     * Whether image optimisation is enabled site-wide.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('mod_casestudy', 'optimizeimages');
    }

    /**
     * Whether images should also be optimised when restoring a backup.
     *
     * @return bool
     */
    public static function is_enabled_on_restore(): bool {
        return self::is_enabled() && (bool) get_config('mod_casestudy', 'optimizeonrestore');
    }

    /**
     * Configured maximum longest edge in pixels.
     *
     * @return int
     */
    public static function get_max_edge(): int {
        $value = (int) get_config('mod_casestudy', 'optimizemaxedge');
        return $value > 0 ? $value : self::DEFAULT_MAX_EDGE;
    }

    /**
     * Configured JPEG quality (clamped to a sane 1-100 range).
     *
     * @return int
     */
    public static function get_quality(): int {
        $value = (int) get_config('mod_casestudy', 'optimizequality');
        if ($value < 1 || $value > 100) {
            $value = self::DEFAULT_QUALITY;
        }
        return $value;
    }

    /**
     * Optimise every image in a single file area (component is always mod_casestudy).
     *
     * @param int $contextid Context id
     * @param string $filearea File area name (e.g. field_12)
     * @param int $itemid Item id (e.g. submission id)
     * @param int|null $maxedge Override max edge, or null to read config
     * @param int|null $quality Override quality, or null to read config
     * @return \stdClass Stats object: ->processed, ->optimized, ->bytesbefore, ->bytesafter
     */
    public static function optimize_area(int $contextid, string $filearea, int $itemid,
            ?int $maxedge = null, ?int $quality = null): \stdClass {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_casestudy', $filearea, $itemid, 'id', false);
        return self::optimize_files($fs, $files, $maxedge, $quality);
    }

    /**
     * Optimise a set of stored files.
     *
     * @param \file_storage $fs File storage
     * @param \stored_file[] $files Files to consider
     * @param int|null $maxedge Override max edge, or null to read config
     * @param int|null $quality Override quality, or null to read config
     * @param bool $apply When false, only estimate the result without modifying any file (dry run)
     * @param callable|null $progress Optional callback(\stdClass $stats, \stored_file $file, bool $changed)
     * @return \stdClass Stats object: ->processed, ->optimized, ->bytesbefore, ->bytesafter
     */
    public static function optimize_files(\file_storage $fs, array $files, ?int $maxedge = null,
            ?int $quality = null, bool $apply = true, ?callable $progress = null): \stdClass {
        $maxedge = $maxedge ?? self::get_max_edge();
        $quality = $quality ?? self::get_quality();

        $stats = (object) ['processed' => 0, 'optimized' => 0, 'bytesbefore' => 0, 'bytesafter' => 0];

        foreach ($files as $file) {
            if ($file->is_directory() || $file->get_filesize() == 0) {
                continue;
            }
            $stats->processed++;
            $before = $file->get_filesize();

            if ($apply) {
                $changed = self::optimize_stored_file($fs, $file, $maxedge, $quality);
                $newsize = $changed ? $changed->get_filesize() : null;
            } else {
                $newsize = self::would_optimize($file, $maxedge, $quality);
            }

            $stats->bytesbefore += $before;
            if ($newsize !== null) {
                $stats->optimized++;
                $stats->bytesafter += $newsize;
            } else {
                $stats->bytesafter += $before;
            }

            if ($progress !== null) {
                $progress($stats, $file, $newsize !== null);
            }
        }

        return $stats;
    }

    /**
     * Estimate the optimised size of a file without modifying it (for dry runs).
     *
     * @param \stored_file $file File to evaluate
     * @param int $maxedge Longest edge cap in pixels
     * @param int $quality JPEG quality (0-100)
     * @return int|null The new size in bytes, or null if the file would be left unchanged
     */
    public static function would_optimize(\stored_file $file, int $maxedge, int $quality): ?int {
        if ($file->is_directory() || $file->get_filesize() == 0) {
            return null;
        }
        $result = self::reencode($file->get_content(), $maxedge, $quality);
        return $result === null ? null : strlen($result);
    }

    /**
     * Optimise a single stored file in place, replacing it when smaller.
     *
     * @param \file_storage $fs File storage
     * @param \stored_file $file File to optimise
     * @param int $maxedge Longest edge cap in pixels
     * @param int $quality JPEG quality (0-100)
     * @return \stored_file|null The replacement file, or null if the file was left unchanged
     */
    public static function optimize_stored_file(\file_storage $fs, \stored_file $file, int $maxedge,
            int $quality): ?\stored_file {
        $result = self::reencode($file->get_content(), $maxedge, $quality);
        if ($result === null) {
            return null;
        }

        $contextid = $file->get_contextid();
        $component = $file->get_component();
        $filearea = $file->get_filearea();
        $itemid = $file->get_itemid();
        $filepath = $file->get_filepath();
        $filename = $file->get_filename();

        // Write the optimised bytes to a temporary name first, so the original is never destroyed
        // before its replacement exists (these are clinical images — losing one is unacceptable).
        $tempname = $filename . '.casestudyopt';
        if ($leftover = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $tempname)) {
            $leftover->delete();
        }

        $filerecord = [
            'contextid' => $contextid,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => $filepath,
            'filename' => $tempname,
            'userid' => $file->get_userid(),
            'author' => $file->get_author(),
            'license' => $file->get_license(),
            'source' => $file->get_source(),
            'timecreated' => $file->get_timecreated(),
        ];

        $newfile = $fs->create_file_from_string($filerecord, $result);

        // Now that the replacement is safely stored, remove the original and take its name.
        $file->delete();
        $newfile->rename($filepath, $filename);

        return $newfile;
    }

    /**
     * Re-encode raw image data: bake in EXIF rotation and downscale to the max edge.
     *
     * Only JPEG and PNG are handled; everything else (PDF, SVG, GIF, ...) is left untouched.
     * Returns null when the image is already within bounds and correctly oriented, or when the
     * re-encoded result would not actually be smaller — so callers never grow a file.
     *
     * @param string $data Raw file bytes
     * @param int $maxedge Longest edge cap in pixels
     * @param int $quality JPEG quality (0-100)
     * @return string|null Optimised image bytes, or null to leave the original untouched
     */
    protected static function reencode(string $data, int $maxedge, int $quality): ?string {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesizefromstring($data);
        if ($info === false) {
            return null;
        }
        [$width, $height] = $info;
        $type = $info[2];
        if ($width < 1 || $height < 1) {
            return null;
        }

        $ispng = ($type === IMAGETYPE_PNG);
        if ($type !== IMAGETYPE_JPEG && !$ispng) {
            // Leave PDFs, SVGs, GIFs (possibly animated) and unknown types alone.
            return null;
        }

        $orientation = $ispng ? 1 : self::read_exif_orientation($data);
        $needsrotate = in_array($orientation, [3, 6, 8], true);
        $needsresize = max($width, $height) > $maxedge;

        if (!$needsresize && !$needsrotate) {
            // Already optimal; do not re-compress and lose quality for nothing.
            return null;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }

        // Bake in EXIF orientation so we can safely strip the metadata.
        if ($needsrotate) {
            $angle = $orientation === 3 ? 180 : ($orientation === 6 ? -90 : 90);
            $rotated = imagerotate($src, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($src);
                $src = $rotated;
                $width = imagesx($src);
                $height = imagesy($src);
            }
        }

        // Compute target size, never upscaling.
        $scale = min(1, $maxedge / max($width, $height));
        $targetw = max(1, (int) round($width * $scale));
        $targeth = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($targetw, $targeth);
        if ($ispng) {
            // Keep transparency for PNGs.
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetw, $targeth, $width, $height);
        imagedestroy($src);

        ob_start();
        if ($ispng) {
            // Lossless; compression level 6 is a good speed/size balance.
            imagepng($dst, null, 6);
        } else {
            imagejpeg($dst, null, $quality);
        }
        $output = ob_get_clean();
        imagedestroy($dst);

        if ($output === false || $output === '' || strlen($output) >= strlen($data)) {
            // No saving (e.g. a tiny image we only rotated) — keep the original bytes.
            return null;
        }

        return $output;
    }

    /**
     * Read the EXIF orientation flag from JPEG bytes (1 when unavailable).
     *
     * @param string $data Raw JPEG bytes
     * @return int EXIF orientation value (1-8)
     */
    protected static function read_exif_orientation(string $data): int {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        // exif_read_data needs a seekable source, so stage the bytes in the per-request temp dir.
        $tmpfile = make_request_directory() . '/casestudy_exif_probe';
        if (file_put_contents($tmpfile, $data) === false) {
            return 1;
        }

        $exif = @exif_read_data($tmpfile);
        if (is_array($exif) && !empty($exif['Orientation'])) {
            return (int) $exif['Orientation'];
        }
        return 1;
    }

    /**
     * Collect ids of all file-field upload files, optionally limited to one context.
     *
     * Used by the bulk optimiser (CLI / adhoc task) to walk existing uploads. File-field uploads
     * live in areas named field_<fieldid>; rich-text and other areas are intentionally excluded.
     *
     * @param int|null $contextid Restrict to a single module context, or null for site-wide
     * @return int[] File ids
     */
    public static function get_field_file_ids(?int $contextid = null): array {
        global $DB;

        $select = "component = :component AND " . $DB->sql_like('filearea', ':area') . "
                   AND filename <> '.' AND filesize > 0";
        $params = ['component' => 'mod_casestudy', 'area' => 'field\\_%'];
        if ($contextid !== null) {
            $select .= " AND contextid = :contextid";
            $params['contextid'] = $contextid;
        }

        return $DB->get_fieldset_select('files', 'id', $select, $params);
    }
}
