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
 * Template editor renderable
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class template_editor implements renderable, templatable {
    private $manager;
    private $templatename;

    public function __construct($manager, $templatename) {
        $this->manager = $manager;
        $this->templatename = $templatename;
    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;

        $data = [
            'title' => get_string('header' . $this->templatename, 'mod_casestudy'),
            'sesskey' => sesskey(),
            'disableeditor' => true,
            'url' => $PAGE->url->out(false),
        ];

        $usehtmleditor = false;
        $disableeditor = false;

        if ($this->templatename !== 'csstemplate') {
            $usehtmleditor = true;
            $disableeditor = true;
        }

        $data['usehtmleditor'] = $usehtmleditor;
        $data['disableeditor'] = $disableeditor;

        $data['toolbar'] = $this->get_toolbar_data($output);
        $data['editors'] = $this->get_editors_data($usehtmleditor);

        return $data;
    }

    private function get_toolbar_data(renderer_base $output) {
        // Get tags based on template type
        $tags = $this->manager->get_available_tags($this->templatename);

        $toolbar = [
            'tags' => [],
            'reseturl' => new moodle_url('/mod/casestudy/templates.php', [
                'id' => $this->manager->get_cm()->id,
                'mode' => $this->templatename,
                'sesskey' => sesskey(),
            ]),
        ];

        // CSS template doesn't have tags
        if ($this->templatename === 'csstemplate') {
            return $toolbar;
        }

        foreach ($tags as $category => $categorytags) {
            $toolbar['tags'][] = [
                'category' => get_string('tagcategory_' . $category, 'mod_casestudy'),
                'items' => array_map(function ($tag, $description) {
                    return ['tag' => $tag, 'description' => $description];
                }, array_keys($categorytags), $categorytags),
            ];
        }

        return $toolbar;
    }

    private function get_editors_data($usehtmleditor) {
        global $PAGE;

        $result = [];

        editors_head_setup();

        $format = FORMAT_PLAIN;
        if ($usehtmleditor) {
            $format = FORMAT_HTML;
        }

        $editor = editors_get_preferred_editor($format);
        $templatecontent = $this->manager->get_template($this->templatename);
        $result[] = $this->generate_editor_data(
            $editor,
            $this->templatename,
            $this->templatename,
            $templatecontent
        );

        return $result;
    }

    private function generate_editor_data($editor, $name, $title, $content) {
        $editordata = [
            'name' => $name,
            'title' => get_string($title, 'mod_casestudy'),
        ];

        $editorcontent = $content;

        $editoroptions = [
            'subdirs' => false,
            'maxfiles' => 0,
            'context' => $this->manager->get_context(),
        ];

        $editor->use_editor($name, $editoroptions);
        $editordata['content'] = $editorcontent;

        return $editordata;
    }
}
