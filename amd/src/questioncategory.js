export const init = () => {
    'use strict';

    document.removeEventListener('change', handleChange);
    document.addEventListener('change', handleChange);
};

const handleChange = function(e) {
    let node = e.target.closest('#id_category, #id_recurse');
    if (node) {
        e.preventDefault();
        window.onbeforeunload = null;
        let form = e.target.closest('form');
        form.submit();
    }
};
