# Case Study activity module for Moodle (mod_casestudy)

A Moodle activity module that lets teachers build structured case study
activities from configurable fields (text, rich text, dropdowns, checkboxes,
radio buttons, file uploads, and section headings). Students complete the
case study fields and submit their work for grading, with support for
templates, activity-level overrides, completion rules, notifications, and a
dedicated grading interface.

Full user documentation is available in the [`docs/`](docs/index.html)
directory.

## Features

- Configurable case study fields with drag-and-drop ordering
- Student submission workflow with draft and final submission states
- Grading interface with per-student navigation
- Reusable templates for case study structures
- User-level overrides for availability and due dates
- Custom completion rules and scheduled notification tasks
- Backup and restore support, including image recovery tooling

## Requirements

- Moodle 5.0 or later (see `version.php` for the exact required version)

## Installation

1. Copy this repository into `mod/casestudy` within your Moodle installation.
2. Visit **Site administration → Notifications** to complete the upgrade.

## Licence

This plugin is proprietary software. Copyright (c) Skin Cancer College
Australasia (SCCA), all rights reserved. It is **not** released under the
GNU General Public License or any other open-source licence. See
[LICENCE](LICENCE) for the full terms.

## Acknowledgements

- **LMS Ace** — acknowledged for the original plugin development on which
  this module was founded.
- **Vernon Spain** — rebuilt the plugin for Skin Cancer College Australasia.
