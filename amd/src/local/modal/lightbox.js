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
 * Lightbox modal for file field to display the image in lightbox.
 *
 * @module     mod_casestudy/local/modal/lightbox
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

import Modal from 'core/modal';

/**
 * The Lightbox Modal
 *
 * @class
 * @extends Modal
 */
export default class ModalLightBox extends Modal {

    static TYPE = 'LIGHTBOX';
    static TEMPLATE = 'mod_casestudy/local/modal/lightbox';

    /**
     * Register all event listeners.
     */
    registerEventListeners() {
        // Call the parent registration.
        super.registerEventListeners();

        // Register to close on cancel.
        this.registerCloseOnCancel();
    }
}

ModalLightBox.registerModalType();
