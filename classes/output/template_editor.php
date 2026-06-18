<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Template editor renderable
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                'sesskey' => sesskey()
            ]),
        ];

        // CSS template doesn't have tags
        if ($this->templatename === 'csstemplate') {
            return $toolbar;
        }

        foreach ($tags as $category => $categorytags) {
            $toolbar['tags'][] = [
                'category' => get_string('tagcategory_' . $category, 'mod_casestudy'),
                'items' => array_map(function($tag, $description) {
                    return ['tag' => $tag, 'description' => $description];
                }, array_keys($categorytags), $categorytags)
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