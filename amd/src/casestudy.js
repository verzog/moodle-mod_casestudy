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
 * Case study.
 *
 * @module     mod_casestudy/casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

import SortableList from 'core/sortable_list';

const SELECTORS = {
    regions: {
        fieldsList: '[data-table-uniqueid="casestudy-fields-table"] tbody ',
    },
};

export const sortableFieldsList = () => {
    new SortableList(SELECTORS.regions.fieldsList, {isHorizontal: false});
};
