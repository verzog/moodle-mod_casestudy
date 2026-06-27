#!/usr/bin/env python3
# Copyright (c) Skin Cancer College Australasia.
# All rights reserved.
#
# This file is part of a proprietary plugin developed by Skin Cancer College
# Australasia for use with Moodle. It is NOT free software and is NOT released
# under the GNU General Public License. Unauthorised copying, distribution,
# modification, or use of this file, in whole or in part, via any medium, is
# strictly prohibited without the prior written permission of Skin Cancer
# College Australasia. The software is provided "as is", without warranty of any
# kind, express or implied.

"""Report how many mod_casestudy file-field images a Moodle backup contains.

A backup produced by the buggy older plugin captures zero case study uploads
(it annotates a dead 'content' area instead of field_<id>), so use this to
confirm a *fresh* backup actually includes the images before trusting it.

Usage:
    verify_casestudy_backup.py <backup.mbz | files.xml | extracted_backup_dir>

Exit code 0 when case study images are present, 1 when none are found.
"""

import os
import re
import sys
import tarfile

FILE_RE = re.compile(r"<file\b.*?</file>", re.S)


def _tag(block, name):
    """Return the text of a single <name>...</name> tag, or '' when absent."""
    match = re.search(r"<%s>(.*?)</%s>" % (name, name), block, re.S)
    return match.group(1).strip() if match else ""


def _human(size):
    """Format a byte count as a short human-readable string."""
    size = float(size)
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if size < 1024 or unit == "TB":
            return "%.1f %s" % (size, unit)
        size /= 1024


def _collect_from_mbz(path):
    """Read files.xml and every casestudy.xml out of an .mbz (gzipped tar)."""
    files_xml = ""
    casestudy_xmls = []
    with tarfile.open(path, "r:*") as tar:
        for member in tar:
            if not member.isfile():
                continue
            name = member.name.lstrip("./")
            if name == "files.xml":
                files_xml = tar.extractfile(member).read().decode("utf-8", "replace")
            elif re.search(r"activities/casestudy_\d+/casestudy\.xml$", name):
                casestudy_xmls.append(
                    tar.extractfile(member).read().decode("utf-8", "replace"))
    return files_xml, casestudy_xmls


def _collect_from_dir(path):
    """Read files.xml and every casestudy.xml out of an extracted backup tree."""
    files_xml = ""
    casestudy_xmls = []
    fx = os.path.join(path, "files.xml")
    if os.path.isfile(fx):
        files_xml = open(fx, encoding="utf-8", errors="replace").read()
    for root, _dirs, names in os.walk(path):
        if os.path.basename(root).startswith("casestudy_") and "casestudy.xml" in names:
            casestudy_xmls.append(
                open(os.path.join(root, "casestudy.xml"), encoding="utf-8", errors="replace").read())
    return files_xml, casestudy_xmls


def _field_shortnames(casestudy_xmls):
    """Map field id -> shortname (file-type fields) from casestudy.xml content."""
    names = {}
    for xml in casestudy_xmls:
        for m in re.finditer(r"<field id=\"(\d+)\">(.*?)</field>", xml, re.S):
            if _tag(m.group(2), "type") == "file":
                names[m.group(1)] = _tag(m.group(2), "shortname")
    return names


def main():
    if len(sys.argv) != 2:
        sys.stderr.write(__doc__)
        return 2

    target = sys.argv[1]
    if os.path.isdir(target):
        files_xml, casestudy_xmls = _collect_from_dir(target)
    elif target.endswith("files.xml"):
        files_xml = open(target, encoding="utf-8", errors="replace").read()
        casestudy_xmls = []
    else:
        files_xml, casestudy_xmls = _collect_from_mbz(target)

    if not files_xml:
        sys.stderr.write("Could not find files.xml in %s\n" % target)
        return 2

    shortnames = _field_shortnames(casestudy_xmls)

    # Tally mod_casestudy file-field uploads per area.
    per_area = {}
    for block in FILE_RE.findall(files_xml):
        if _tag(block, "component") != "mod_casestudy":
            continue
        area = _tag(block, "filearea")
        if not area.startswith("field_") or _tag(block, "filename") == ".":
            continue
        size = int(_tag(block, "filesize") or 0)
        count, total = per_area.get(area, (0, 0))
        per_area[area] = (count + 1, total + size)

    print("Backup: %s" % target)
    if casestudy_xmls:
        print("Case study activities found in backup: %d" % len(casestudy_xmls))
    print("")

    if not per_area:
        print("RESULT: FAIL - no mod_casestudy field_<id> images found in this backup.")
        print("This is the signature of a backup made by the pre-fix plugin: the")
        print("case study uploads were dropped at backup time and cannot be restored.")
        return 1

    total_count = 0
    total_bytes = 0
    print("%-14s %-32s %8s  %s" % ("area", "field", "images", "size"))
    print("-" * 70)
    for area in sorted(per_area):
        count, size = per_area[area]
        fieldid = area.split("_", 1)[1]
        print("%-14s %-32s %8d  %s" % (area, shortnames.get(fieldid, "?"), count, _human(size)))
        total_count += count
        total_bytes += size
    print("-" * 70)
    print("RESULT: OK - %d case study image(s), %s total." % (total_count, _human(total_bytes)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
