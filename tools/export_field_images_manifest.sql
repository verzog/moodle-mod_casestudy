-- Copyright (c) Skin Cancer College Australasia.
-- All rights reserved.
--
-- This file is part of a proprietary plugin developed by Skin Cancer
-- College Australasia for use with Moodle. It is NOT free software and is
-- NOT released under the GNU General Public License.
--
-- Unauthorised copying, distribution, modification, or use of this file,
-- in whole or in part, via any medium, is strictly prohibited without the
-- prior written permission of Skin Cancer College Australasia. The software
-- is provided "as is", without warranty of any kind, express or implied.
--
-- ---------------------------------------------------------------------------
-- Read-only export of case study FILE-FIELD image references from a legacy site.
--
-- Why this exists: old mod_casestudy versions never annotated the per-field
-- upload areas (field_<fieldid>) for backup, so their course backups contain the
-- rich-text/feedback images but NONE of the student file-field uploads. When the
-- source site cannot be upgraded, the uploads still exist in its database and
-- filedir and can be recovered without touching the plugin.
--
-- This query produces the CSV manifest consumed by
--   cli/import_field_images_from_manifest.php
-- on the upgraded target site. Pair it with tools/collect_filedir_blobs.sh, which
-- copies the referenced blobs out of the source moodledata filedir.
--
-- It is strictly read-only (a single SELECT). Run it against the SOURCE database.
--
-- Required manifest columns (see manifest_image_importer::REQUIRED_COLUMNS):
--   casestudy, field, old_submissionid, email, filename, contenthash
-- The extra columns (old_attempt, filesize) are ignored by the importer but help
-- with verification and any future attempt-based pairing.
--
-- BEFORE RUNNING:
--   1. Replace the table prefix `mdl_` below with your site's $CFG->prefix.
--   2. Optionally scope to one course by uncommenting the `cs.course` filter.
--   3. Export the result as CSV WITH A HEADER ROW (see tools README / the guide
--      docs/recovering-field-images-from-legacy-site.md for per-DB export tips).
--
-- MySQL / MariaDB version follows. For PostgreSQL, change CONCAT('field_', cf.id)
-- to ('field_' || cf.id) and keep the LIKE as-is (or use
-- f.filearea ~ '^field_[0-9]+$').
-- ---------------------------------------------------------------------------

SELECT
    cs.name       AS casestudy,        -- activity name  -> matched to target module by name
    cf.shortname  AS field,            -- field shortname -> matched to target file field
    f.itemid      AS old_submissionid, -- source submission id (pairs to target submission)
    sub.attempt   AS old_attempt,      -- source attempt number (stable across restore)
    u.email       AS email,            -- student email  -> matched to target user
    f.filename    AS filename,         -- stored file name
    f.contenthash AS contenthash,      -- SHA-1 of contents -> matched to downloaded blob
    f.filesize    AS filesize          -- for verification only
FROM mdl_files f
JOIN mdl_casestudy_fields cf
      ON f.filearea = CONCAT('field_', cf.id)
     AND cf.type = 'file'
JOIN mdl_casestudy_submissions sub ON sub.id = f.itemid
JOIN mdl_casestudy cs              ON cs.id = sub.casestudyid
JOIN mdl_user u                    ON u.id = sub.userid
WHERE f.component = 'mod_casestudy'
  AND f.filearea LIKE 'field\_%'   -- underscore escaped so it is a literal, not a wildcard
  AND f.filename <> '.'            -- skip directory placeholder records
  AND f.filesize > 0              -- skip empty entries
  -- AND cs.course = 2151         -- optional: restrict to one course id
ORDER BY cs.name, u.email, sub.attempt, f.itemid, f.filename;
