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
 * A repo for the search partial in the submissions page.
 *
 * @module    mod_casestudy/submission_confirmation
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification', 'core/prefetch', 'core/templates', 'core/str',
        'core_form/changechecker'],
function(notification, Prefetch, Templates, Str, ChangeChecker) {

    const SELECTOR = {
        attemptSubmitButton: 'body.path-mod-casestudy .btn-finishsubmission',
        attemptSubmitForm: 'form#frm-finishsubmission',
    };

    const TEMPLATES = {
        submissionConfirmation: 'mod_casestudy/submission_confirmation',
    };

    const registerEventListeners = (unAnsweredQuestions) => {
        const submitAction = document.querySelector(SELECTOR.attemptSubmitButton);
        if (!submitAction) {
            return;
        }

        submitAction.addEventListener('click', async (e) => {
            e.preventDefault();

            try {
                const content = await Templates.render(TEMPLATES.submissionConfirmation, {
                    hasunanswered: unAnsweredQuestions > 0,
                    totalunanswered: unAnsweredQuestions
                });

                await notification.saveCancelPromise(
                    await Str.get_string('submission_confirmation', 'mod_casestudy'),
                    content,
                    await Str.get_string('finishandsubmit', 'mod_casestudy')
                );

                const form = submitAction.closest(SELECTOR.attemptSubmitForm);

                if (!form) {
                    return;
                }

                let hiddenFinish = form.querySelector('input[name="finish"]');
                if (!hiddenFinish) {
                    hiddenFinish = document.createElement('input');
                    hiddenFinish.type = 'hidden';
                    hiddenFinish.name = 'finish';
                    form.appendChild(hiddenFinish);
                }
                hiddenFinish.value = 1;

                if (ChangeChecker && typeof ChangeChecker.markFormSubmitted === 'function') {
                    ChangeChecker.markFormSubmitted(form);
                } else {
                    form.dataset.formSubmitted = "true";
                }

                form.submit();

            } catch {
                return;
            }
        });
    };

    const registerDirectSubmit = () => {
        const submitAction = document.querySelector(SELECTOR.attemptSubmitButton);
        const form = document.querySelector(SELECTOR.attemptSubmitForm);

        if (!submitAction || !form || submitAction.dataset.directBound) {
            return;
        }

        submitAction.dataset.directBound = '1';

        submitAction.addEventListener('click', (e) => {
            e.preventDefault();

            let hiddenFinish = form.querySelector('input[name="finish"]');
            if (!hiddenFinish) {
                hiddenFinish = document.createElement('input');
                hiddenFinish.type = 'hidden';
                hiddenFinish.name = 'finish';
                form.appendChild(hiddenFinish);
            }
            hiddenFinish.value = 1;

            if (ChangeChecker && typeof ChangeChecker.markFormSubmitted === 'function') {
                ChangeChecker.markFormSubmitted(form);
            } else {
                form.dataset.formSubmitted = "true";
            }

            window.onbeforeunload = null;

            form.submit();
        });
    };

    return {
        init: function(unAnsweredQuestions, requireSubmit) {

            if (!requireSubmit) {
                registerDirectSubmit();
                return;
            }

            // POPUP MODE
            Prefetch.prefetchStrings('mod_casestudy', [
                'finishandsubmit',
                'submission_confirmation'
            ]);
            Prefetch.prefetchTemplate(TEMPLATES.submissionConfirmation);

            registerEventListeners(unAnsweredQuestions || 0);
        }
    };
});
