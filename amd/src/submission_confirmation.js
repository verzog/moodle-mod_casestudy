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
 * A repo for the search partial in the submissions page.
 *
 * @module    mod_casestudy/submission_confirmation
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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

        submitAction.addEventListener('click', async(e) => {
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
