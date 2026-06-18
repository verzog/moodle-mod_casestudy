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
 * File field + inline image lightbox.
 *
 * @module     mod_casestudy/field_file
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

import ModalLightBox from 'mod_casestudy/local/modal/lightbox';

// Containers that may hold a renderable submission body (default view, custom
// template, or any field render). Used to scope the delegated click handler
// so we don't intercept arbitrary images elsewhere on the page (user picture,
// site logo, etc.).
const CONTENT_SELECTOR = [
    '.casestudy-submission-view',
    '.casestudy-submission-content',
    '.casestudy-submission-template',
    '.casestudy-template-output'
].join(', ');

// Things we never want to lightbox even if they live inside the submission body.
const SKIP_SELECTOR = [
    '.userpicture',
    '.icon',
    '[data-region="title"]',
    '.modal'
].join(', ');

/**
 * Open the lightbox with the image at the given src.
 *
 * @param {String} src Absolute or pluginfile URL of the image.
 * @param {String} title Modal heading (falls back to the alt text or empty).
 * @returns {Promise<Modal>}
 */
const openImageLightbox = (src, title) => {
    const body = document.createElement('div');
    body.className = 'casestudy-lightbox-image-wrapper text-center';
    const img = document.createElement('img');
    img.src = src;
    img.alt = title || '';
    img.className = 'casestudy-lightbox-image img-fluid';
    body.appendChild(img);

    return ModalLightBox.create({
        title: title || '',
        body: body.outerHTML,
        removeOnClose: true,
        show: true
    });
};

// Guard against double-binding when init() is called from more than one render.
let delegatedHandlerBound = false;

const bindDelegatedHandler = () => {
    if (delegatedHandlerBound) {
        return;
    }
    delegatedHandlerBound = true;

    document.addEventListener('click', function(e) {
        // Legacy file-field markup (magnifying-glass span over a thumbnail).
        const legacyTrigger = e.target.closest('[data-toggle="casestudy-file-modal"]');
        if (legacyTrigger) {
            const wrapper = legacyTrigger.closest('[data-modal="lightbox"]');
            if (wrapper) {
                e.preventDefault();
                const src = wrapper.dataset?.modalImageSrc
                    || extractSrcFromHtml(wrapper.dataset?.modalContent);
                const title = wrapper.dataset?.modalTitle || '';
                if (src) {
                    openImageLightbox(src, title);
                }
                return;
            }
        }

        // Generic case: any image inside the submission body opens the lightbox.
        const img = e.target.closest('img');
        if (!img) {
            return;
        }
        if (!img.closest(CONTENT_SELECTOR)) {
            return;
        }
        if (img.closest(SKIP_SELECTOR)) {
            return;
        }
        // Skip if the image is inside a link that goes somewhere else — defer to the link.
        const enclosingLink = img.closest('a[href]');
        if (enclosingLink && enclosingLink.getAttribute('href') !== img.src) {
            return;
        }

        e.preventDefault();
        openImageLightbox(
            img.currentSrc || img.src,
            img.getAttribute('data-modal-title') || img.alt || ''
        );
    });
};

/**
 * Extract the src attribute from a stored HTML <img> blob, for the legacy
 * data-modal-content shape file_field used to write.
 *
 * @param {String|undefined} html
 * @returns {String}
 */
const extractSrcFromHtml = (html) => {
    if (!html) {
        return '';
    }
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const img = tmp.querySelector('img');
    return img ? img.getAttribute('src') || '' : '';
};

/**
 * Mark images inside the submission body so the lightbox affordance is visible.
 * Called on init and on any subsequent template re-render.
 */
const decorateImages = () => {
    document.querySelectorAll(CONTENT_SELECTOR).forEach(scope => {
        scope.querySelectorAll('img').forEach(img => {
            if (img.closest(SKIP_SELECTOR)) {
                return;
            }
            img.classList.add('casestudy-lightbox-target');
        });
    });
};

export const init = () => {
    bindDelegatedHandler();
    decorateImages();
};

export const completionValueSelector = (categorySelector, valueSelector) => {

    const categoryField = document.getElementById(categorySelector);
    const valueField = document.getElementById(valueSelector);

    if (!categoryField || !valueField) {
        return;
    }

    categoryField.addEventListener('change', function() {
        const selectedCategory = this.value;

        Array.from(valueField.options).forEach(option => {
            if (option.value.startsWith(selectedCategory + '-')) {
                option.disabled = false;
            } else {
                option.disabled = true;
            }
        });
    });
};


