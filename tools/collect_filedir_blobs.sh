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

# Locate the contenthash column by header name (order-independent).
hashcol=$(head -1 "$manifest" | tr ',' '\n' | sed 's/^"//;s/"$//' | grep -n -x 'contenthash' | cut -d: -f1 || true)
if [ -z "${hashcol:-}" ]; then
    echo "No 'contenthash' column found in manifest header." >&2
    exit 1
fi

copied=0
missing=0
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
done < <(tail -n +2 "$manifest" | cut -d',' -f"$hashcol" | tr -d '"' | sort -u)

echo "----------------------------------------"
echo "Copied:  $copied blob(s) -> $out"
echo "Missing: $missing blob(s) (listed above, if any)"
