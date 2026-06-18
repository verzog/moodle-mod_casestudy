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
 * Simple navigation for case study grading - redirects on user change
 *
 * @module     mod_casestudy/grading_navigation_simple
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/str', 'core/form-autocomplete', 'core/ajax'],
    function($, notification, str, autocomplete, ajax) {

    /**
     * Initialize the grading navigation
     *
     * @param {String} selector Selector for navigation container
     * @param {Number} currentSubmissionId Current submission ID
     * @param {Number} cmid Course module ID
     * @param {Number} casestudyid Case study ID
     */
    var init = function(selector, currentSubmissionId, cmid, casestudyid) {
        var region = $(selector);
        var submissions = [];

        // Load submissions
        ajax.call([{
            methodname: 'mod_casestudy_get_submissions_for_grading',
            args: {casestudyid: casestudyid, cmid: cmid},
            done: function(data) {
                submissions = data;
                populateSelect(data, currentSubmissionId);
                updateButtons();
            },
            fail: notification.exception
        }]);

        /**
         * Populate the select dropdown
         */
        function populateSelect(data, selectedId) {
            var select = region.find('[data-action=change-user]');
            select.empty();

            data.forEach(function(submission) {
                var option = $('<option></option>')
                    .attr('value', submission.id)
                    .text(submission.fullname + ' - Attempt ' + submission.attempt);

                if (submission.id === selectedId) {
                    option.attr('selected', 'selected');
                }

                select.append(option);
            });

            // Set the value explicitly before enhancing
            select.val(selectedId);

            // Initialize autocomplete after populating and setting value
            str.get_string('changeuser', 'mod_casestudy').done(function(s) {
                autocomplete.enhance('[data-action=change-user]', false, '', s);
                // Ensure the autocomplete shows the correct initial value
                select.val(selectedId).trigger('change.select2');
            }).fail(notification.exception);
        }

        /**
         * Update button states
         */
        function updateButtons() {
            var select = region.find('[data-action=change-user]');
            var currentId = parseInt(select.val(), 10);
            var currentIndex = -1;

            for (var i = 0; i < submissions.length; i++) {
                if (submissions[i].id === currentId) {
                    currentIndex = i;
                    break;
                }
            }

            var prevBtn = region.find('[data-action="previous-user"]');
            var nextBtn = region.find('[data-action="next-user"]');

            // Update previous button
            if (currentIndex <= 0) {
                prevBtn.attr('disabled', 'disabled');
            } else {
                prevBtn.removeAttr('disabled');
            }

            // Update next button
            if (currentIndex >= submissions.length - 1) {
                nextBtn.attr('disabled', 'disabled');
            } else {
                nextBtn.removeAttr('disabled');
            }

            // Update count
            updateCount(currentIndex + 1, submissions.length);
        }

        /**
         * Update the count display
         */
        function updateCount(current, total) {
            str.get_string('xofy', 'mod_casestudy', {x: current, y: total}).done(function(s) {
                region.find('[data-region="user-count-summary"]').text(s);
            }).fail(notification.exception);
        }

        /**
         * Navigate to a submission
         */
        function navigateToSubmission(submissionId) {
            var url = new URL(window.location.href);
            url.searchParams.set('submissionid', submissionId);
            window.location.href = url.toString();
        }

        // Handle user selection change
        region.find('[data-action="change-user"]').on('change', function() {
            var newSubmissionId = parseInt($(this).val(), 10);
            if (newSubmissionId && newSubmissionId !== currentSubmissionId) {
                navigateToSubmission(newSubmissionId);
            }
        });

        // Handle previous button
        region.find('[data-action="previous-user"]').on('click', function(e) {
            e.preventDefault();
            if ($(this).attr('disabled')) {
                return;
            }

            var select = region.find('[data-action=change-user]');
            var currentId = parseInt(select.val(), 10);

            for (var i = 0; i < submissions.length; i++) {
                if (submissions[i].id === currentId && i > 0) {
                    navigateToSubmission(submissions[i - 1].id);
                    break;
                }
            }
        });

        // Handle next button
        region.find('[data-action="next-user"]').on('click', function(e) {
            e.preventDefault();
            if ($(this).attr('disabled')) {
                return;
            }

            var select = region.find('[data-action=change-user]');
            var currentId = parseInt(select.val(), 10);

            for (var i = 0; i < submissions.length; i++) {
                if (submissions[i].id === currentId && i < submissions.length - 1) {
                    navigateToSubmission(submissions[i + 1].id);
                    break;
                }
            }
        });
    };

    return {
        init: init
    };
});
