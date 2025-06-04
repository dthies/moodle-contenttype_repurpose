import Fragment from 'core/fragment';
import ModalFactory from 'core/modal_factory';
import notification from 'core/notification';
import {get_string as getString} from 'core/str';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 */
export const init = (contextid, library) => {
    'use strict';

    ModalFactory.create({
        large: false,
        title: getString('switchbank', 'core_question'),
        type: ModalFactory.types.CANCEL,
        body: 'content'
    }).then(function(modal) {
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

        return true;
    }).fail(notification.exception);
};
