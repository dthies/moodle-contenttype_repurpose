import Fragment from 'core/fragment';
import notification from 'core/notification';
import templates from 'core/templates';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 */
const init = (contextid, library, cmid) => {
    'use strict';

    document.removeEventListener('change', handleChange.bind(window, contextid, library, cmid));
    document.addEventListener('change', handleChange.bind(window, contextid, library, cmid));
};

/**
 * Update form from change event
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 * @param {event} e Event
 */
const handleChange = (contextid, library, cmid, e) => {
    if (e.target.closest('form #id_category, form #id_recurse, form #id_question, form [data-action="update"]')) {
        let form = e.target.closest('form');
        e.stopPropagation();
        e.preventDefault();
        updateForm(contextid, library, cmid, form);
    }
};

/**
 * Update the form
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {int} cmid Question bank
 * @param {DOMNode} form Form element to be updated
 */
const updateForm = (contextid, library, cmid, form) => {
        let data = {},
            formdata = new FormData(form),
            params = {
                cmid: cmid,
                contextid: contextid,
                library: library,
                plugin: 'repurpose'
            };

        window.onbeforeunload = null;

        formdata.forEach((value, key) => {
            data[key] = value;
        });
        params.jsonformdata = JSON.stringify(data);

        Fragment.loadFragment('contenttype_repurpose', 'formupdate', contextid, params).done(function(html, js) {
            document.removeEventListener('change', updateForm.bind(window, contextid, library));
            templates.replaceNodeContents(form, html, js);
            form.parentNode.insertBefore(form.firstChild, form);
            form.remove();
        }).fail(notification.exception);
};

export default {
    init: init,
    updateForm: updateForm
};
