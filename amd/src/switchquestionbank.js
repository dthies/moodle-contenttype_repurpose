import Fragment from 'core/fragment';
import Modal from 'core/modal_cancel';
import notification from 'core/notification';
import {get_string as getString} from 'core/str';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 */
export const init = async(contextid, library) => {
    'use strict';

    try {
        const modal = await Modal.create({
            large: false,
            title: getString('switchbank', 'core_question'),
            body: 'content'
        });
        document.addEventListener('click', e => {
            const button = e.target.closest('input[name="switchquestionbank"]');
            const params = {
                contextid: contextid,
                library: library
            };
            if (button) {
                e.stopPropagation();
                e.preventDefault();
                modal.setBody(Fragment.loadFragment(
                    'contenttype_repurpose',
                    'questionbank',
                    contextid,
                    params
                ));
                modal.show();
            }
        }, true);
    } catch (e) {
        notification.exception(e);
    }
};
