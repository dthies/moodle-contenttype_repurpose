import Fragment from 'core/fragment';
import notification from 'core/notification';
import templates from 'core/templates';

/**
 * Initialize listeners
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 */
export const init = (contextid, library) => {
    'use strict';

    document.removeEventListener('change', updateForm.bind(window, contextid, library));
    document.addEventListener('change', updateForm.bind(window, contextid, library));
};

/**
 * Update the form
 *
 * @param {int} contextid Context id of content bank
 * @param {string} library Library identifier
 * @param {event} e Event
 */
const updateForm = function(contextid, library, e) {
    if (e.target.closest('form #id_category, form #id_recurse, form #id_question, form [data-action="update"]')) {
        let data = {},
            form = e.target.closest('form'),
            formdata = new FormData(form),
            params = {
                contextid: contextid,
                jsonformdata: JSON.stringify(data),
                library: library,
                plugin: 'repurpose'
            };

        e.stopPropagation();
        e.preventDefault();
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
    }
};
