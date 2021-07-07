export const init = () => {
    'use strict';

    document.removeEventListener('click', previewQuestion);
    document.addEventListener('click', previewQuestion);
};

const previewQuestion = function(e) {
    let anchor = e.target.closest('a[data-action="preview"]');
    if (anchor && anchor.href) {
        let url = anchor.href,
            height = Math.min(600, window.screen.height),
            width = Math.min(600, window.screen.width),
            xOffset = (window.screen.width - width) / 2,
            yOffset = (window.screen.height - height) / 2;
        e.stopPropagation();
        e.preventDefault();
        window.open(url, 'repurpose_preview', 'width=' + width + ',height=' + height
            + ',left=' + xOffset + ',top=' + yOffset
            + ',xOffset=' + xOffset + ',yOffset=' + yOffset
        );
    }
};
