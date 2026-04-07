(function () {
    'use strict';

    var ZWNJ = '\u200C';
    var wrap = document.getElementById('wp-content-wrap');

    function isTextTabActive() {
        return wrap !== null && wrap.classList.contains('html-active');
    }

    function insertZwnjAtCursor() {
        var textarea = document.activeElement && document.activeElement.id === 'content'
            ? document.activeElement
            : document.getElementById('content');
        if (!textarea) {
            return;
        }

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;

        textarea.value = value.substring(0, start) + ZWNJ + value.substring(end);
        textarea.selectionStart = textarea.selectionEnd = start + 1;

        // Trigger input event for WP dirty-form detection
        var event = new Event('input', { bubbles: true });
        textarea.dispatchEvent(event);
    }

    document.addEventListener('keydown', function (e) {
        if (!isTextTabActive()) {
            return;
        }

        var isShiftSpace = e.shiftKey && !e.ctrlKey && !e.metaKey && !e.altKey
            && (e.key === ' ' || e.code === 'Space' || e.keyCode === 32);

        if (isShiftSpace) {
            e.preventDefault();
            insertZwnjAtCursor();
        }
    }, true);
})();
