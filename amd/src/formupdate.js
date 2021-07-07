import Fragment from 'core/fragment';
import notification from 'core/notification';
import templates from 'core/templates';

export const init = (contextid, library) => {
    'use strict';

    document.removeEventListener('change', updateForm.bind(window, contextid, library));
    document.addEventListener('change', updateForm.bind(window, contextid, library));
};

const updateForm = function(contextid, library, e) {
    if (e.target.closest('#id_category, #id_recurse, #id_question, [data-action="update"]')) {
        e.stopPropagation();
        window.onbeforeunload = null;
        let form = e.target;
        while (form.nodeName.toLowerCase() !== 'form') {
            form = form.parentNode;
        }
        let formdata = new FormData(form),
            data = {};
        formdata.forEach((value, key) => {
            data[key] = value;
        });
        let params = {
            contextid: contextid,
            jsonformdata: JSON.stringify(data),
            library: library,
            plugin: 'repurpose'
        };
        Fragment.loadFragment('contenttype_repurpose', 'formupdate', contextid, params).done(function(html, js) {
            templates.replaceNodeContents(form, html, js);
            form.parentNode.insertBefore(form.firstChild, form);
            form.remove();
        }).fail(notification.exception);
    }
};
