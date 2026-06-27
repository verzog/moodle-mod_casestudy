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
 * Adhoc task to optimise images already stored in case study file fields.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\task;

use mod_casestudy\local\image_optimizer;

/**
 * Walk existing file-field uploads in batches and optimise them once.
 *
 * Queue it with no custom data to process the whole site; it snapshots the work list on first run
 * and re-queues itself with the remaining ids until done, so a large library never has to finish
 * within a single cron run.
 */
class optimize_existing_images extends \core\task\adhoc_task {

    /** @var int How many files to process per run before re-queuing. */
    const BATCH_SIZE = 200;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskoptimizeimages', 'mod_casestudy');
    }

    /**
     * Process a batch of files and re-queue the remainder.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $maxedge = isset($data->maxedge) ? (int) $data->maxedge : image_optimizer::get_max_edge();
        $quality = isset($data->quality) ? (int) $data->quality : image_optimizer::get_quality();

        // Snapshot the work list on the first run so re-created (already optimised) files are not
        // picked up again on later runs.
        if (isset($data->fileids) && is_array($data->fileids)) {
            $fileids = $data->fileids;
        } else {
            $fileids = image_optimizer::get_field_file_ids();
        }

        $batch = array_slice($fileids, 0, self::BATCH_SIZE);
        $remaining = array_slice($fileids, self::BATCH_SIZE);

        if (empty($batch)) {
            mtrace('mod_casestudy: no images left to optimise.');
            return;
        }

        $fs = get_file_storage();
        $files = [];
        foreach ($batch as $fileid) {
            if ($stored = $fs->get_file_by_id($fileid)) {
                $files[] = $stored;
            }
        }

        $stats = image_optimizer::optimize_files($fs, $files, $maxedge, $quality);
        $saved = $stats->bytesbefore - $stats->bytesafter;
        mtrace(sprintf(
            'mod_casestudy: optimised %d/%d images this batch, saved %s (%d files remaining).',
            $stats->optimized,
            $stats->processed,
            display_size(max(0, $saved)),
            count($remaining)
        ));

        if (!empty($remaining)) {
            $next = new self();
            $next->set_custom_data((object) [
                'fileids' => array_values($remaining),
                'maxedge' => $maxedge,
                'quality' => $quality,
            ]);
            \core\task\manager::queue_adhoc_task($next);
        } else {
            mtrace('mod_casestudy: image optimisation complete.');
        }
    }
}
