import FormUpdate from 'contenttype_repurpose/formupdate';
import Fragment from 'core/fragment';
import ModalEvents from 'core/modal_events';
import Modal from 'core/modal_save_cancel';
import notification from 'core/notification';
import {get_string as getString} from 'core/str';
import templates from 'core/templates';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 */
export const init = async(contextid, library, cmid) => {
    'use strict';
    let form;

    try {
        const modal = await Modal.create({
            large: false,
            title: getString('addfile', 'contenttype_repurpose'),
            body: ''
        });
        const root = modal.getRoot();
        root.on('submit', function(e) {
            let data = new FormData(root.find('form').get(0));
            e.stopPropagation();
            e.preventDefault();
            saveForm(contextid, library, cmid, form, data, modal);
            modal.hide();
        });
        root.on(ModalEvents.save, function(e) {
            let data = new FormData(root.find('form').get(0));
            e.stopPropagation();
            e.preventDefault();
            saveForm(contextid, library, cmid, form, data, modal);
            modal.hide();
        });

        document.addEventListener('click', e => {
            let button = e.target.closest('[data-action="edit"], button[name="backgroundimage[addfile]"]');
            if (button) {
                e.stopPropagation();
                e.preventDefault();
                form = e.target.closest('form');
                editForm(contextid, library, cmid, form, button, modal);
            }
        }, true);
    } catch (e) {
        notification.exception(e);
    }

    document.addEventListener('click', edit.bind(window, contextid, cmid, library));
};
/**
 * Open editing modal
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 * @param {DOMNode} form Node for main form
 * @param {DOMNode} button The button that was clicked
 * @param {object} modal
 */
const editForm = function(contextid, library, cmid, form, button, modal) {
    'use strict';

    let formdata = new FormData(form),
        background = formdata.get('background'),
        data = {
            draftid: formdata.get('draftid'),
        },
        params = {
            contextid: contextid,
            library: library
        };
    if (background) {
        data.license = JSON.parse(background).metadata.license;
    }
    params.jsonformdata = JSON.stringify(data);
    Fragment.loadFragment('contenttype_repurpose', 'addfile', contextid, params).done(function(html, js) {
        modal.show();
        templates.replaceNodeContents(modal.getRoot().find('.modal-body'), html, js);
    }).fail(notification.exception);
};

/**
 * Submit form and handle response
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 * @param {DOMNode} form Node for main form
 * @param {FormData} data Modal data
 */
const saveForm = function(contextid, library, cmid, form, data) {
    'use strict';

    let formdata = {},
        params = {
            contextid: contextid,
            library: library
        };
    data.forEach((value, key) => {
        formdata[key] = value;
    });
    params.jsonformdata = JSON.stringify(formdata);
    Fragment.loadFragment('contenttype_repurpose', 'addfile', contextid, params).done(function(html) {
        let result;
        try {
            result = JSON.parse(html);
        } catch (e) {
            result = JSON.parse(form.querySelector('input[name="background"]').getAttribute('value'));
        }
        result.metadata.license = data.get('license');
        form.querySelector('input[name="background"]').setAttribute('value', JSON.stringify(result));
        FormUpdate.updateForm(contextid, library, cmid, form);
    }).fail(notification.exception);
};

/**
 * Handle editing action
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 * @param {event} e Event
 */
export const edit = function(contextid, library, cmid, e) {
    'use strict';

    let button = e.target.closest('[data-action="delete"]');
    if (button) {
        let form = button.closest('form');
        e.stopPropagation();
        e.preventDefault();

        form.querySelector('input[name="background"]').setAttribute('value', '');
        FormUpdate.updateForm(contextid, library, cmid, form);
    }
};
