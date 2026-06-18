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
 * Fields table for Case Study module
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use table_sql;
use moodle_url;
use pix_icon;
use core_table\dynamic as dynamic_table;

/**
 * Dynamic table for displaying case study fields
 */
class fields_table extends table_sql implements dynamic_table {
    /** @var object $cm Course module object */
    protected $cm;

    /** @var object $context Context object */
    protected $context;

    /** @var string $sesskey Session key for forms */
    protected $sesskey;

    /**
     * Constructor
     *
     * @param string $uniqueid Unique identifier for the table
     * @param object $cm Course module
     * @param object $context Context
     */
    public function __construct($uniqueid, $cm, $context) {
        parent::__construct($uniqueid);

        $this->cm = $cm;
        $this->context = $context;
        $this->sesskey = sesskey();
    }

    /**
     * Configure columns, headers and SQL then defer to the parent table renderer.
     *
     * @param int $pagesize Page size for the table.
     * @param bool $useinitialsbar Whether to show the initials bar.
     * @param string $downloadhelpbutton Optional download button HTML.
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {

        // Define columns.
        $columns = [
            'sortorder',
            'name',
            'type',
            'required',
            'category',
            'showlistview',
            'actions',
        ];

        // Define column headers
        $headers = [
            get_string('order', 'mod_casestudy'),
            get_string('fieldname', 'mod_casestudy'),
            get_string('fieldtype', 'mod_casestudy'),
            get_string('required'),
            get_string('category', 'core'),
            get_string('showlistview', 'mod_casestudy'),
            get_string('actions', 'core'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Configure table properties
        $this->sortable(true, 'sortorder', SORT_ASC);
        $this->collapsible(false);
        $this->set_attribute('class', 'casestudy-fields-table table table-striped table-hover');

        // Set SQL query
        $this->set_sql(
            'id, sortorder, name, type, required, category, showlistview',
            '{casestudy_fields}',
            'casestudyid = :casestudyid',
            ['casestudyid' => $this->cm->instance]
        );

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }


    /**
     * Check if user has capability to manage fields
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('mod/casestudy:managefields', $this->context);
    }

    /**
     * Format sort order column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_sortorder($row) {

        return \html_writer::tag('span', '<i class="fa fa-up-down-left-right"></i>', [
            'data-field-id' => $row->id,
            'data-sort-order' => $row->sortorder,
            'class' => 'move',
        ]);
    }

    /**
     * Format field name column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_name($row) {
        global $OUTPUT;

        $title = new \core\output\inplace_editable(
            'mod_casestudy',
            'casestudyname',
            $row->id,
            true,
            \html_writer::tag('strong', format_string($row->name)),
            $row->name,
            get_string('casestudyname', 'casestudy'),
            get_string('newvaluefor', 'casestudy', $row->name)
        );

        return $OUTPUT->render($title);
    }

    /**
     * Format field type column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_type($row) {
        $typename = get_string('fieldtype_' . $row->type, 'mod_casestudy');
        $icon = $this->get_field_type_icon($row->type);
        return \html_writer::span($icon . ' ' . $typename, 'field-type-display');
    }

    /**
     * Format required column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_required($row) {
        if ($row->required) {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-check']) . ' ' . get_string('yes'),
                'badge badge-success',
                ['title' => get_string('required', 'core')]
            );
        } else {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-times']) . ' ' . get_string('no'),
                'badge badge-light text-muted',
                ['title' => get_string('notrequired', 'mod_casestudy')]
            );
        }
    }

    /**
     * Format category column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_category($row) {
        if ($row->category) {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-tag']) . ' ' . get_string('yes'),
                'badge badge-info',
                ['title' => get_string('iscategory', 'mod_casestudy')]
            );
        } else {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-minus']) . ' ' . get_string('no'),
                'badge badge-light text-muted',
                ['title' => get_string('notcategory', 'mod_casestudy')]
            );
        }
    }

    /**
     * Format show in list view column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_showlistview($row) {
        if ($row->showlistview) {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-eye']) . ' ' . get_string('yes'),
                'badge badge-primary',
                ['title' => get_string('showlistview', 'mod_casestudy')],
            );
        } else {
            return \html_writer::span(
                \html_writer::tag('i', '', ['class' => 'fa fa-eye-slash']) . ' ' . get_string('no'),
                'badge badge-light text-muted',
                ['title' => get_string('hidelistview', 'mod_casestudy')],
            );
        }
    }

    /**
     * Format actions column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_actions($row) {
        global $OUTPUT;

        $actions = [];

        // 'action' => 'moveup',
        $manageurl = new moodle_url('/mod/casestudy/fields/manage.php', [
            'id' => $this->cm->id, 'fieldid' => $row->id, 'sesskey' => $this->sesskey]);

        // url.
        $editurl = new moodle_url('/mod/casestudy/fields/edit.php', ['id' => $this->cm->id, 'fieldid' => $row->id, 'type' => $row->type]);

        // Edit action.
        $actions[] = $OUTPUT->action_icon(
            $editurl,
            new pix_icon('i/customfield', get_string('edit')),
            null,
            ['title' => get_string('edit', 'core'), 'class' => 'btn btn-sm btn-outline-secondary']
        );

        $manageurl->param('action', 'clone');
        $actions[] = $OUTPUT->action_icon(
            $manageurl,
            new pix_icon('t/copy', get_string('clone', 'mod_casestudy')),
            null,
            ['title' => get_string('clone', 'mod_casestudy'), 'class' => 'btn btn-sm btn-outline-primary']
        );

        // Delete action.
        $manageurl->param('action', 'delete');
        if (class_exists('core\output\actions\confirm_action')) {
            $confirmaction = new \core\output\actions\confirm_action(get_string('confirmdelete', 'mod_casestudy'));
        } else {
            $confirmaction = new \confirm_action(get_string('confirmdelete', 'mod_casestudy'));
        }
        $actions[] = $OUTPUT->action_icon(
            $manageurl,
            new pix_icon('t/delete', get_string('delete')),
            $confirmaction,
            [
                'title' => get_string('delete', 'core'),
                'class' => 'btn btn-sm btn-outline-danger',
            ]
        );

        return \html_writer::div(implode(' ', $actions), 'btn-group', ['role' => 'group']);
    }

    /**
     * Get icon for field type
     *
     * @param string $type Field type
     * @return string HTML icon
     */
    private function get_field_type_icon($type) {
        global $PAGE;

        $renderer = $PAGE->get_renderer('mod_casestudy');
        $types = $renderer->get_field_types_with_icons();
        $icons = array_column($types, 'icon', 'value');

        $iconclass = isset($icons[$type]) ? $icons[$type] : 'fa fa-question';

        return \html_writer::tag('i', '', ['class' => "fa fa-" . $iconclass]);
    }

    /**
     * Check if field can be moved up
     *
     * @param object $row Table row
     * @return bool
     */
    private function can_move_up($row) {
        return $row->sortorder > 1;
    }

    /**
     * Check if field can be moved down
     *
     * @param object $row Table row
     * @return bool
     */
    private function can_move_down($row) {
        // Get maximum sort order
        global $DB;
        $maxorder = $DB->get_field(
            'casestudy_fields',
            'MAX(sortorder)',
            ['casestudyid' => $this->cm->instance]
        );
        return $row->sortorder < $maxorder;
    }

    /**
     * Override to add custom CSS classes and attributes to table rows
     *
     * @param object $row
     * @return string
     */
    public function get_row_class($row) {
        $classes = [];

        if ($row->required) {
            $classes[] = 'field-required';
        }

        if ($row->category) {
            $classes[] = 'field-category';
        }

        if ($row->type == 'sectionheading') {
            $classes[] = 'field-section-heading text-muted';
        }

        return implode(' ', $classes);
    }

    /**
     * Override to add custom attributes to table rows
     *
     * @param object $row
     * @return array
     */
    public function get_row_attributes($row) {
        return [
            'data-field-id' => $row->id,
            'data-sort-order' => $row->sortorder,
        ];
    }
}
