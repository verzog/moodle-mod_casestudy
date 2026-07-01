#!/bin/bash
# Copyright (c) Skin Cancer College Australasia.
# All rights reserved.
#
# This file is part of a proprietary plugin developed by Skin Cancer
# College Australasia for use with Moodle. It is NOT free software and is
# NOT released under the GNU General Public License.
#
# Unauthorised copying, distribution, modification, or use of this file,
# in whole or in part, via any medium, is strictly prohibited without the
# prior written permission of Skin Cancer College Australasia. The software
# is provided "as is", without warranty of any kind, express or implied.
#
# ---------------------------------------------------------------------------
# Copy the image blobs referenced by an exported manifest out of a Moodle
# moodledata filedir into a flat output folder, ready to hand to
#   cli/import_field_images_from_manifest.php --filesdir=<output>
#
# Read-only with respect to the source: it only copies FROM the filedir.
#
# Moodle stores file content addressed by SHA-1 contenthash at
#   <filedir>/<hash[0:2]>/<hash[2:4]>/<hash>
# The importer re-hashes whatever it finds in the output folder, so naming each
# copy by its hash is enough (and de-duplicates shared content automatically).
#
# Usage:
#   tools/collect_filedir_blobs.sh MANIFEST.csv /path/to/moodledata/filedir OUTPUT_DIR
# ---------------------------------------------------------------------------
set -euo pipefail

if [ "$#" -ne 3 ]; then
    echo "Usage: $0 MANIFEST.csv /path/to/moodledata/filedir OUTPUT_DIR" >&2
    exit 1
fi

manifest="$1"
filedir="$2"
out="$3"

[ -r "$manifest" ] || { echo "Manifest not readable: $manifest" >&2; exit 1; }
[ -d "$filedir" ]  || { echo "Filedir not found: $filedir" >&2; exit 1; }
mkdir -p "$out"

# Extract the unique contenthash values with a real CSV parser (python3), so a comma
# inside a quoted field (e.g. an activity name or filename like "Case, week 1") does not
# shift columns the way a plain `cut -d,` would. Matches how the importer reads the file.
command -v python3 >/dev/null 2>&1 || { echo "python3 is required to parse the manifest CSV." >&2; exit 1; }
hashes=$(python3 - "$manifest" <<'PY'
import csv, sys
seen = set()
with open(sys.argv[1], newline='', encoding='utf-8') as fh:
    reader = csv.DictReader(fh)
    if not reader.fieldnames or 'contenthash' not in reader.fieldnames:
        sys.stderr.write("No 'contenthash' column found in manifest header.\n")
        sys.exit(2)
    for row in reader:
        h = (row.get('contenthash') or '').strip()
        if h and h not in seen:
            seen.add(h)
            print(h)
PY
) || { echo "Failed to parse manifest CSV." >&2; exit 1; }

copied=0
missing=0
# Here-string (not a pipe) so the counters update in this shell.
while IFS= read -r h; do
    [ -z "$h" ] && continue
    src="$filedir/${h:0:2}/${h:2:2}/$h"
    if [ -f "$src" ]; then
        cp -n "$src" "$out/$h"
        copied=$((copied + 1))
    else
        echo "MISSING  $src"
        missing=$((missing + 1))
    fi
done <<< "$hashes"

echo "----------------------------------------"
echo "Copied:  $copied blob(s) -> $out"
echo "Missing: $missing blob(s) (listed above, if any)"
