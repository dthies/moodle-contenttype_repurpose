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
 * @param {int} cmid Question bank
 */
export const init = (contextid, library, cmid) => {
    'use strict';
    let form;

    ModalFactory.create({
        large: false,
        title: getString('editmedia', 'contenttype_repurpose'),
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
            saveForm(contextid, library, cmid, form, data, modal);
            modal.hide();
        });

        document.addEventListener('click', e => {
            let button = e.target.closest('[data-action="edit"], button[name="addfile"]');
            if (button) {
                e.stopPropagation();
                e.preventDefault();
                form = e.target.closest('form');
                editForm(contextid, library, cmid, form, button, modal);
            }
        }, true);

        return true;
    }).fail(notification.exception);

    document.addEventListener('click', edit.bind(window, contextid, library, cmid));
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

    let buttons = form.querySelectorAll('[data-action="edit"]'),
        formdata = new FormData(form),
        data = {
            draftid: button.matches('[name="addfile"]') && formdata.get('draftid'),
            key: buttons.length
        },
        params = {
            cmid: cmid,
            contextid: contextid,
            library: library
        },
        mediafiles = JSON.parse(formdata.get('mediafiles') || '[]');

    for (let i = 0; i < buttons.length; i++) {
        if (button.contains(buttons[i])) {
            data.key = i;
            data.title = mediafiles[i].metadata.title || null;
            data.license = mediafiles[i].metadata.license || null;
        }
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
 * @param {object} modal
 */
const saveForm = function(contextid, library, cmid, form, data, modal) {
    'use strict';

    let formdata = new FormData(form),
        key = data.get('key'),
        mediafiles = JSON.parse(formdata.get('mediafiles'));
    if (!mediafiles[key]) {
        let formdata = {},
            params = {
                cmid: cmid,
                contextid: contextid,
                library: library
            };
        data.forEach((value, key) => {
            formdata[key] = value;
        });
        params.jsonformdata = JSON.stringify(formdata);
        Fragment.loadFragment('contenttype_repurpose', 'addfile', contextid, params).done(function(html, js) {
            try {
                let media = JSON.parse(html);
                media.metadata.license = data.get('license');
                mediafiles[key] = media;
                form.querySelector('input[name="mediafiles"]').setAttribute('value', JSON.stringify(mediafiles));
                FormUpdate.updateForm(contextid, library, cmid, form);
            } catch (e) {
                modal.show();
                templates.replaceNodeContents(modal.getRoot().find('.modal-body'), html, js);
            }
        }).fail(notification.exception);
    } else {
        mediafiles[key].metadata.title = data.get('title');
        mediafiles[key].metadata.license = data.get('license');
        form.querySelector('input[name="mediafiles"]').setAttribute('value', JSON.stringify(mediafiles));
        FormUpdate.updateForm(contextid, library, cmid, form);
    }
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

    let button = e.target.closest('[data-action="delete"], [data-action="left"], [data-action="right"]');
    if (button) {
        let buttons,
            data = {},
            form = e.target.closest('form'),
            formdata = new FormData(form),
            mediafiles = JSON.parse(formdata.get('mediafiles'));
        e.stopPropagation();
        e.preventDefault();

        formdata.forEach((value, key) => {
            data[key] = value;
        });
        switch (button.getAttribute('data-action')) {
            case 'delete':
                buttons = form.querySelectorAll('[data-action="delete"]');
                for (let i = 0; i < buttons.length; i++) {
                    if (button.contains(buttons.item(i))) {
                        mediafiles.splice(i, 1);
                    }
                }
                break;
            case 'left':
                buttons = form.querySelectorAll('[data-action="left"]');
                for (let i = 0; i < buttons.length; i++) {
                    if (button.contains(buttons.item(i))) {
                        mediafiles.splice(i, 2, mediafiles[i + 1], mediafiles[i]);
                    }
                }
                break;
            case 'right':
                buttons = form.querySelectorAll('[data-action="right"]');
                for (let i = 0; i < buttons.length; i++) {
                    if (button.contains(buttons.item(i))) {
                        mediafiles.splice(i, 2, mediafiles[i + 1], mediafiles[i]);
                    }
                }
                break;
            default:
                break;
        }
        form.querySelector('input[name="mediafiles"]').setAttribute('value', JSON.stringify(mediafiles));
        FormUpdate.updateForm(contextid, library, cmid, form);
    }
};
