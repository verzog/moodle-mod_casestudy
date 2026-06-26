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
 * Site administration settings for the case study module.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

use mod_casestudy\local\image_optimizer;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_casestudy/optimizeheading',
        get_string('imageoptimisation', 'mod_casestudy'),
        get_string('imageoptimisation_desc', 'mod_casestudy')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_casestudy/optimizeimages',
        get_string('optimizeimages', 'mod_casestudy'),
        get_string('optimizeimages_desc', 'mod_casestudy'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'mod_casestudy/optimizemaxedge',
        get_string('optimizemaxedge', 'mod_casestudy'),
        get_string('optimizemaxedge_desc', 'mod_casestudy'),
        image_optimizer::DEFAULT_MAX_EDGE,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_casestudy/optimizequality',
        get_string('optimizequality', 'mod_casestudy'),
        get_string('optimizequality_desc', 'mod_casestudy'),
        image_optimizer::DEFAULT_QUALITY,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_casestudy/optimizeonrestore',
        get_string('optimizeonrestore', 'mod_casestudy'),
        get_string('optimizeonrestore_desc', 'mod_casestudy'),
        1
    ));
}
