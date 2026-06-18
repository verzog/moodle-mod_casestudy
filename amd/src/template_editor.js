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
 * Template editor JavaScript for inserting placeholders into textareas.
 *
 * @module   mod_casestudy/template_editor
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('mod_casestudy/template_editor', ['jquery'], function($) {

    /**
     * The last focused textarea element
     * @type {HTMLTextAreaElement|null}
     */
    var lastFocusedTextarea = null;

    /**
     * Initialize textarea focus tracking
     */
    const initTextareaTracking = function() {
        // Track which textarea was last focused
        $('.template-editor-form textarea').on('focus', function() {
            lastFocusedTextarea = this;
        });

        // Set initial focus on first textarea if none selected
        if (!lastFocusedTextarea) {
            var firstTextarea = $('.template-editor-form textarea').first();
            if (firstTextarea.length) {
                firstTextarea.focus();
                lastFocusedTextarea = firstTextarea[0];
            }
        }
    };

    /**
     * Insert text at cursor position in textarea
     * @param {HTMLTextAreaElement} textarea The textarea element
     * @param {string} text The text to insert
     */
    const insertAtCursor = function(textarea, text) {
        if (!textarea) {
            return;
        }

        var scrollPos = textarea.scrollTop;
        var startPos = textarea.selectionStart;
        var endPos = textarea.selectionEnd;
        var textBefore = textarea.value.substring(0, startPos);
        var textAfter = textarea.value.substring(endPos, textarea.value.length);

        textarea.value = textBefore + text + textAfter;
        textarea.scrollTop = scrollPos;

        // Set cursor position after inserted text
        var newCursorPos = startPos + text.length;
        textarea.selectionStart = newCursorPos;
        textarea.selectionEnd = newCursorPos;

        textarea.focus();
        $(textarea).trigger('change');
    };

    /**
     * Initialize tag click handlers
     */
    const initTagInsert = function() {
        // Handle clicks on tag items
        $(document).on('click', '.tag-insert', function(e) {
            e.preventDefault();

            var tag = $(this).data('tag');
            if (!tag) {
                return;
            }

            // Copy to clipboard for convenience
            navigator.clipboard.writeText(tag).catch(function() {
                // Clipboard write failed, ignore silently
            });

            // Get the target textarea (last focused or first one)
            var targetTextarea = lastFocusedTextarea;
            if (!targetTextarea) {
                targetTextarea = $('.template-editor-form textarea').first()[0];
            }

            if (targetTextarea) {
                insertAtCursor(targetTextarea, tag);
            }
        });

        // Also handle clicks on the code element inside tag-insert
        $(document).on('click', '.tag-insert code', function(e) {
            e.preventDefault();
            // Trigger the parent's click handler
            $(this).parent('.tag-insert').trigger('click');
        });
    };

    /**
     * Initialize toolbar collapse/expand if needed
     */
    const initToolbarToggle = function() {
        // Add collapse/expand functionality for tag categories if there are many
        $('.tag-category h6').on('click', function() {
            $(this).next('ul').slideToggle('fast');
            $(this).toggleClass('collapsed');
        });
    };

    /**
     * Add helper features like copy to clipboard
     */
    const initHelperFeatures = function() {
        // Add hover effect to show tags are clickable
        $('.tag-insert').css('cursor', 'pointer');

        // Optional: Add tooltips if needed
        $('.tag-insert').attr('title', function() {
            return $(this).data('tag') + ' - Click or drag to insert';
        });
    };

    /**
     * Initialize drag and drop functionality for placeholders
     */
    const initDragAndDrop = function() {
        // Make tag-insert elements draggable
        $('.tag-insert').attr('draggable', 'true');

        // Handle drag start
        $(document).on('dragstart', '.tag-insert', function(e) {
            var tag = $(this).data('tag');
            if (!tag) {
                e.preventDefault();
                return;
            }

            // Set the data to be transferred
            e.originalEvent.dataTransfer.setData('text/plain', tag);
            e.originalEvent.dataTransfer.effectAllowed = 'copy';

            // Add visual feedback
            $(this).addClass('dragging');
        });

        // Handle drag end
        $(document).on('dragend', '.tag-insert', function() {
            $(this).removeClass('dragging');
        });

        // Make textareas droppable
        $('.template-editor-form textarea').on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            $(this).addClass('drag-over');
        });

        $('.template-editor-form textarea').on('dragleave', function() {
            $(this).removeClass('drag-over');
        });

        $('.template-editor-form textarea').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');

            var tag = e.originalEvent.dataTransfer.getData('text/plain');
            if (!tag) {
                return;
            }

            // Get cursor position from the drop event
            var textarea = this;
            var rect = textarea.getBoundingClientRect();
            var offsetX = e.originalEvent.clientX - rect.left;
            var offsetY = e.originalEvent.clientY - rect.top;

            // Focus the textarea first
            textarea.focus();
            insertAtCursor(textarea, tag);
        });

        // Make casestudy-submission-single editor droppable (for HTML editors)
        $('.casestudy-submission-single').on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            $(this).addClass('drag-over');
        });

        $('.casestudy-submission-single').on('dragleave', function() {
            $(this).removeClass('drag-over');
        });

        $('.casestudy-submission-single').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');

            var tag = e.originalEvent.dataTransfer.getData('text/plain');
            if (!tag) {
                return;
            }

            // If there's a textarea in the submission-single editor
            var targetTextarea = $(this).find('textarea').first()[0];
            if (!targetTextarea) {
                targetTextarea = lastFocusedTextarea;
            }

            if (targetTextarea) {
                insertAtCursor(targetTextarea, tag);
            }
        });
    };

    return {
        /**
         * Initialize the template editor
         */
        init: function() {
            initTextareaTracking();
            initTagInsert();
            initToolbarToggle();
            initHelperFeatures();
            initDragAndDrop();
        }
    };
});
