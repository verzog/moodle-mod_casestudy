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
 * @module    mod_casestudy/repository
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

import ajax from 'core/ajax';

/**
 * Given a course ID, we want to fetch the learners within this casestudyment.
 *
 * @method userFetch
 * @param {int} casestudyid ID of the casestudyment.
 * @param {int} groupid ID of the selected group.
 * @return {object} jQuery promise
 */
export const userFetch = (casestudyid, groupid) => {
    const request = {
        methodname: 'mod_casestudy_list_participants',
        args: {
            casestudyid: casestudyid,
            groupid: groupid,
            filter: '',
        },
    };
    return ajax.call([request])[0];
};
