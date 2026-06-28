# Vendor "v3" image-handling analysis

Analysis of the third vendor-supplied build of `mod_casestudy` (the one that was
never applied), focused on image handling, compared against the current repo.

| | Vendor build (this zip) | Current repo |
|---|---|---|
| `$plugin->version` | `2026032700` (Mar 2026) | `2026062604` (Jun 2026) |

**Bottom line:** this vendor build predates the repo's image-handling work and is a
regression on every count. Nothing in its image handling is worth applying. The only
item worth acting on independently is the `field_*` access-control gap (#5), which was
present in the repo too and is fixed alongside this report.

## What the vendor build gets right

- **Upload** — `file_field` uses Moodle's `filemanager`, saving drafts into a per-field
  area `field_<fieldid>` keyed by submission id
  (`submission_edit_form::save_area_files` → `file_field::save_area_files` →
  `file_save_draft_area_files`). File count, max size and accepted types are configurable.
- **Display** — images render as `responsive-img` thumbnails with a magnifier overlay and
  a `mod_casestudy/field_file` lightbox modal; non-images render as links.
- **Serving** — `casestudy_pluginfile()` serves `field_*` areas after `require_login`.

## Problems found (ranked)

### 1. 🔴 Backup/restore silently loses all file-field images

Uploaded files live in dynamic `field_<fieldid>` areas keyed by submission id, but the
backup step only annotates static areas:

```php
// backup/moodle2/backup_casestudy_stepslib.php
$content->annotate_files('mod_casestudy', 'content', 'id');   // wrong area + wrong key
// ...no field_<id> areas are ever annotated
```

Because nothing annotates the `field_<id>` areas, **every file-field image is dropped on
course backup, restore, import, or duplication** — with no error.

The current repo fixes this: it enumerates file-type field ids and annotates each
`field_<fieldid>` area, then on restore remaps `field_<oldid>` → `field_<newid>` via a
staging area, and migrates legacy `content`-area uploads.

### 2. 🟠 No image optimisation at all

The entire `image_optimizer` subsystem is absent — no
`classes/local/image_optimizer.php`, no `optimize_existing_images` adhoc task, no
`settings.php`, no CLI/tools. Consequences:

- Full-resolution photos stored as-is (large filedir/DB, slow pages).
- **EXIF rotation is never baked in, so portrait phone photos display sideways.**

The repo downscales to a max edge, bakes in EXIF rotation, and re-encodes on upload, on
restore, and retroactively for existing files.

### 3. 🟠 Images are force-downloaded instead of shown inline

The vendor serves every file with forced attachment disposition:

```php
send_stored_file($file, 0, 0, true, $options);   // literal true = always force download
```

This fights the inline `<img>`/lightbox display. The repo passes the real flag
(`send_stored_file($file, 0, 0, $forcedownload, $options)`) so images render inline.

### 4. 🟡 Loose filearea match

`strpos($filearea, 'field_') !== false` matches `field_` *anywhere* in the string rather
than as a prefix. Harmless today but fragile; a prefix check (`=== 0` / `str_starts_with`)
is correct. Still present in the repo too.

### 5. 🟡 Access-control gap on `field_*` images

The `field_*` branch of `pluginfile` checked only `require_login` + course access — unlike
`submission_richtext`, it did **not** verify the submission belonged to the requesting user
or that they held `viewallsubmissions`. A logged-in course member could fetch another
student's uploaded image by guessing submission id + filename.

This was **not** a vendor regression — the repo had the same gap. It is fixed in the same
change as this report by applying the existing per-submission ownership/capability check to
the `field_*` areas as well as `submission_richtext`.
