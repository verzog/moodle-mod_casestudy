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
 * Output the grading actionbar for this activity.
 *
 * @package   mod_casestudy
 * @copyright 2021 Adrian Greeve <adrian@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\output;

use mod_casestudy\local\casestudy;
use context_module;
use core_course\output\actionbar\group_selector;
use core_course\output\actionbar\user_selector;
use templatable;
use renderable;
use moodle_url;
use core\output\local\dropdown\dialog;


class submission_actionmenu implements renderable, templatable {

    /** @var \mod_casestudy\local\casestudy The casestudy instance. */
    protected $casestudy;

    /** @var \context_module The context of the casestudy instance. */
    protected $context;

    /** @var int The current group id. */
    protected $currentgroup;

    /** @var int The current user id. */
    protected $currentuser;

    /** @var \moodle_url The base url for the action menu. */
    protected $baseurl;

    /** @var array User initials for filtering. */
    protected $userinitials = [];

    /** @var array Additional actions to add to the action menu. */
    protected $additionalactions = [];


    /** @var array Filters to disable. */
    protected $disabledfilters = [];

    /** @var string Current view (submissions or summaries). */
    protected $currentview = 'submissions';

    /**
     * Constructor.
     *
     * @param \mod_casestudy\local\casestudy $casestudy the casestudy instance.
     * @param \context_module $context the context of the casestudy instance.
     * @param int $currentgroup the current group id.
     * @param int $currentuser the current user id.
     * @param \moodle_url $baseurl the base url for the action menu.
     */
    public function __construct(casestudy $casestudy, \moodle_url $baseurl, array $userinitials = [], array $additionalactions = []) {
        $this->casestudy = $casestudy;
        $this->baseurl = $baseurl;
        $this->userinitials = $userinitials;
        $this->additionalactions = $additionalactions;
    }

    /**
     * Set the current view (submissions or summaries).
     *
     * @param string $view The view to set.
     */
    public function set_current_view(string $view): void {
        $this->currentview = $view;
    }

    /**
     * Disable specific filters in the action menu.
     *
     * @param array $filters The filters to disable.
     */
    public function disable_filters($filters) : void {
        $this->disabledfilters = $filters;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output The renderer to use.
     * @return array The data for the template.
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        global $PAGE;


        $userid = optional_param('userid', null, PARAM_INT);
        // If the user ID is set, it indicates that a user has been selected. In this case, override the user search
        // string with the full name of the selected user.
        $usersearch = $userid ? fullname(\core_user::get_user($userid)) : optional_param('search', '', PARAM_NOTAGS);

        $templatecontext = [];

        $resetlink = new moodle_url('/mod/casestudy/view.php', ['id' => $this->casestudy->get_cm()->id, 'action' => 'grading']);
        $groupid = groups_get_course_group($this->casestudy->get_course(), true);

        if (!in_array('user', $this->disabledfilters)) {

            $userselector = new user_selector(
                course: $this->casestudy->get_course(),
                resetlink: $resetlink,
                userid: $userid,
                groupid: $groupid,
                usersearch: $usersearch,
                instanceid: $this->casestudy->casestudyid
            );
            $templatecontext['userselector'] = $userselector->export_for_template($output);
            $PAGE->requires->js_call_amd('mod_casestudy/user', 'init', [$resetlink->out(false)]);
        }

        $hasinitials = !empty($this->userinitials['firstname']) || !empty($this->userinitials['lastname']);
        $additionalparams = ['action' => 'grading', 'id' => $this->casestudy->get_cm()->id];

        if (!empty($userid)) {
            $additionalparams['userid'] = $userid;
        } else if (!empty($usersearch)) {
            $additionalparams['search'] = $usersearch;
        }

        if (!in_array('initials', $this->disabledfilters)) {
            // Initial bars.
            $initialselector = new \core_course\output\actionbar\initials_selector(
                course: $this->casestudy->get_course(),
                targeturl: 'mod/casestudy/view.php',
                firstinitial: $this->userinitials['firstname'] ?? '',
                lastinitial: $this->userinitials['lastname'] ?? '',
                firstinitialparam: 'tifirst',
                lastinitialparam: 'tilast',
                additionalparams: $additionalparams
            );
            $templatecontext['initialselector'] = $initialselector->export_for_template($output);
        }


        if (!in_array('group', $this->disabledfilters)
            && groups_get_activity_groupmode($this->casestudy->get_cm(), $this->casestudy->get_course())) {
            $gs = new group_selector($PAGE->context);
            $templatecontext['groupselector'] = $gs->export_for_template($output);

            $PAGE->requires->js_call_amd(
                'core_course/actionbar/group', 'init', [$resetlink->out(false), $this->casestudy->get_cm()->id]);
        }

        if (!in_array('status', $this->disabledfilters)) {
            // Submission   Status filter.
            ['statusmenu' => $statusmenu, 'currentvalue' => $currentvalue] = $this->get_status_menu();
            $statusselect = new \core\output\select_menu('status', $statusmenu, $currentvalue);
            $statusselect->set_label(get_string('status', 'core'), [], true);
            $templatecontext['statusselector'] = $statusselect->export_for_template($output);
        }

        $templatecontext['actions'] = $this->additionalactions;

        // Add view selector (Submissions vs Summaries).
        $viewmenu = $this->get_view_menu($output);
        if ($viewmenu) {
            $templatecontext['viewselector'] = $viewmenu;
        }

        return $templatecontext;
    }

    /**
     * Get the view selector menu (Submissions vs Summaries).
     *
     * @return array|null The view selector data or null.
     */
    private function get_view_menu($output): ?\stdClass {
        $cm = $this->casestudy->get_cm();
        $context = \context_module::instance($cm->id);

        $submissionsurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);
        $summariesurl = new \moodle_url('/mod/casestudy/summaries.php', ['id' => $cm->id]);

        $viewmenu = [
            $submissionsurl->out(false) => get_string('submissions', 'mod_casestudy'),
        ];

        // Only add summaries option if user has viewallsubmissions capability.
        if (has_capability('mod/casestudy:viewallsubmissions', $context)) {
            $summariesurl = new \moodle_url('/mod/casestudy/summaries.php', ['id' => $cm->id]);
            $viewmenu[$summariesurl->out(false)] = get_string('summaries', 'mod_casestudy');
        }

        // Only show the dropdown if there are multiple options.
        if (count($viewmenu) <= 1) {
            return null;
        }

        $currentvalue = $this->currentview === 'summaries' && isset($summariesurl) ? $summariesurl->out(false) : $submissionsurl->out(false);

        $viewselect = new \core\output\select_menu('view', $viewmenu, $currentvalue);
        $viewselect->set_label(get_string('gradeitem:submissions', 'mod_assign'), [], true);

        return $viewselect->export_for_template($output);
    }

    /**
     * Get the status menu for the grading action menu.
     *
     * @return array An array containing the status menu and the current value.
     */
    private function get_status_menu(): array {
        $statusmenu = [];
        $currentvalue = '';

        $filters = $this->casestudy->get_status_filters();


        $url = new \moodle_url('/mod/casestudy/view.php', [
            'id' => $this->casestudy->get_cm()->id, 'action' => 'grading',
        ]);

        foreach ($filters as $filter) {

            if ($filter['key'] === 'none') {
                // The 'none' filter is not a real filter.
                $filter['key'] = '';
            }

            $url->param('status', $filter['key']);
            $statusmenu[$url->out(false)] = $filter['name'];

            if ($filter['active']) {
                $currentvalue = $url->out(false);
            }
        }

        return [
            'statusmenu' => $statusmenu,
            'currentvalue' => $currentvalue,
        ];
    }
}
