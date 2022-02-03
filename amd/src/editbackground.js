import FormUpdate from 'contenttype_repurpose/formupdate';
import Fragment from 'core/fragment';
import ModalEvents from 'core/modal_events';
import ModalFactory from 'core/modal_factory';
import notification from 'core/notification';
import {get_string as getString} from 'core/str';
import templates from 'core/templates';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 */
export const init = (contextid, library) => {
    'use strict';
    let form;

    ModalFactory.create({
        large: false,
        title: getString('addfile', 'contenttype_repurpose'),
        type: ModalFactory.types.SAVE_CANCEL,
        body: ''
    }).then(function(modal) {
        let root = modal.getRoot();
        root.on('submit', function(e) {
            let data = new FormData(root.find('form').get(0));
            e.stopPropagation();
            e.preventDefault();
            saveForm(contextid, library, form, data, modal);
            modal.hide();
        });
        root.on(ModalEvents.save, function(e) {
            let data = new FormData(root.find('form').get(0));
            e.stopPropagation();
            e.preventDefault();
            saveForm(contextid, library, form, data, modal);
            modal.hide();
        });

        document.addEventListener('click', e => {
            let button = e.target.closest('[data-action="edit"], button[name="backgroundimage[addfile]"]');
            if (button) {
                e.stopPropagation();
                e.preventDefault();
                form = e.target.closest('form');
                editForm(contextid, library, form, button, modal);
            }
        }, true);
    }).fail(notification.exception);

    document.addEventListener('click', edit.bind(window, contextid, library));
};
/**
 * Open editing modal
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {DOMNode} form Node for main form
 * @param {DOMNode} button The button that was clicked
 * @param {object} modal
 */
const editForm = function(contextid, library, form, button, modal) {
    'use strict';

    let formdata = new FormData(form),
        data = {
            draftid: formdata.get('draftid'),
        },
        params = {
            contextid: contextid,
            library: library
        };
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
 * @param {DOMNode} form Node for main form
 * @param {FormData} data Modal data
 * @param {object} modal
 */
const saveForm = function(contextid, library, form, data, modal) {
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
        Fragment.loadFragment('contenttype_repurpose', 'addfile', contextid, params).done(function(html, js) {
            let result = JSON.parse(html);
            if (result) {
                form.querySelector('input[name="background"]').setAttribute('value', result.params.file.path);
                FormUpdate.updateForm(contextid, library, form);
            } else {
                modal.show();
                templates.replaceNodeContents(modal.getRoot().find('.modal-body'), html, js);
            }
        }).fail(notification.exception);
};

/**
 * Handle editing action
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {event} e Event
 */
export const edit = function(contextid, library, e) {
    'use strict';

    let button = e.target.closest('[data-action="delete"]');
    if (button) {
        let form = button.closest('form');
        e.stopPropagation();
        e.preventDefault();

        form.querySelector('input[name="background"]').setAttribute('value', '');
        FormUpdate.updateForm(contextid, library, form);
    }
};
