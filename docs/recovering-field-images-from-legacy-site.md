# Recovering file-field images from a legacy site that can't be upgraded

## The problem

Old `mod_casestudy` versions never annotated the per-field upload areas
(`field_<fieldid>`) for backup. Their course backups therefore contain the
rich-text and grader-feedback images, but **none of the student file-field
uploads** (the "Clinical image", "Macroscopic image", "Pathology report" fields,
etc.). Restoring such a backup brings across only that small handful of images —
the bulk simply aren't in the `.mbz`.

If the source site can be upgraded, the fix is to upgrade it (version
`2026062300`+ annotates the field areas) and take a fresh backup. **This guide is
for when the source site cannot be upgraded** — the uploads still exist in its
database and `moodledata` filedir and can be recovered read-only.

## How to confirm you're hitting this

In an unpacked backup, the activity's `field_<id>` uploads are absent:

```bash
# from the unpacked backup root — should print nothing for a legacy backup:
grep -o '<filearea>field_[0-9]*</filearea>' files.xml | sort -u
```

If that prints nothing while the case study clearly uses file-upload fields, the
uploads were dropped at backup time and this recovery is the path.

## The recovery in one picture

```
 SOURCE (legacy, read-only)                 TARGET (upgraded, restored course)
 ─────────────────────────                  ──────────────────────────────────
 1. run export SQL      ─► manifest.csv ─┐
 2. collect_filedir_blobs ─► images/  ───┼─► copy both over ─► 3. import CLI (dry-run)
                                         │                     4. import CLI (--commit)
```

Nothing is written on the source. On the target, files are only written with
`--commit` (and never overwrite a differing existing file — that's flagged as a
conflict instead).

## Step 1 — export the manifest (SOURCE, read-only)

Run `tools/export_field_images_manifest.sql` against the source database and save
the result as **properly-quoted CSV with a header row**. Edit the table prefix
first (and, optionally, the course filter).

> Do **not** just convert the `mysql` client's tab output to commas (e.g.
> `sed 's/\t/,/g'`): that does not quote or escape values, so an activity name or
> filename containing a comma (`Case, week 1`) shifts the columns and the importer
> (`fgetcsv`) then mis-maps emails/filenames/hashes. Use a CSV-aware exporter:

- MySQL/MariaDB — use MySQL Shell, which emits real CSV:
  ```bash
  mysqlsh --sql --result-format=csv -u USER -p -h HOST DBNAME \
      < mod/casestudy/tools/export_field_images_manifest.sql > casestudy_manifest.csv
  ```
  No `mysqlsh`? Run the query in a GUI/admin tool (phpMyAdmin, Adminer, DBeaver)
  and use its **Export → CSV** (quoted). Or `SELECT ... INTO OUTFILE` with
  `FIELDS OPTIONALLY ENCLOSED BY '"'` if the server allows it.
- PostgreSQL — `\copy` already quotes correctly:
  ```bash
  psql -d DBNAME -c "\copy (<paste the SELECT>) TO 'casestudy_manifest.csv' WITH CSV HEADER"
  ```
  (change `CONCAT('field_', cf.id)` to `('field_' || cf.id)` for Postgres.)

The manifest columns are `casestudy, field, old_submissionid, email, filename,
contenthash` (plus `old_attempt, filesize`, which the importer ignores).

## Step 2 — collect the image blobs (SOURCE, read-only)

```bash
mod/casestudy/tools/collect_filedir_blobs.sh \
    casestudy_manifest.csv /path/to/moodledata/filedir ./casestudy_images
```

This copies each referenced blob out of the filedir into `./casestudy_images/`
(named by contenthash). Any `MISSING` lines mean those specific files are absent
from the source filedir too — genuinely unrecoverable, not a tooling issue.

## Step 3 — transfer both to the target

Copy `casestudy_manifest.csv` and the `casestudy_images/` folder to the upgraded
target site (any path readable by the web/CLI user).

## Step 4 — import (TARGET)

Dry run first — writes nothing, just reports what it would do:

```bash
php mod/casestudy/cli/import_field_images_from_manifest.php \
    --manifest=casestudy_manifest.csv \
    --filesdir=casestudy_images \
    --courseid=<target course id>
```

Review the summary, then apply:

```bash
php mod/casestudy/cli/import_field_images_from_manifest.php \
    --manifest=casestudy_manifest.csv \
    --filesdir=casestudy_images \
    --courseid=<target course id> \
    --commit
```

## How matching works (and what has to line up)

The importer reattaches each file to the target by:

- **student** — source `email` → target user (must be a unique, non-deleted
  account with that email);
- **activity** — source `casestudy` name → target activity of the same name
  (scope with `--courseid` to avoid same-named activities elsewhere);
- **field** — source `field` shortname → target file field of the same shortname;
- **submission** — source submissions are paired to target submissions in id
  order within each (activity, student).

Because the target course was restored from this same source, the activity names,
field shortnames and per-user submission sets already match, so these line up by
construction.

### Caveat: submission pairing needs equal counts

The current importer pairs submissions positionally and **skips a
(student, activity) group if the number of source submissions that carry images
differs from the number of target submissions** (reported as
`Submission-count mismatches`). That happens when a student has some submissions
with no uploaded image. If the dry run shows many such mismatches, tell us: the
importer can be hardened to match submissions by `attempt` (already exported as
`old_attempt`), which removes the equal-count requirement. Files are only ever
written for confidently matched submissions, so a mismatch skips — it never
misfiles.
