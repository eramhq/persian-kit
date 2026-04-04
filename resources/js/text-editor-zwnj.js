(function () {
    'use strict';

    var ZWNJ = '\u200C';
    var wrap = document.getElementById('wp-content-wrap');

    function isTextTabActive() {
        return wrap !== null && wrap.classList.contains('html-active');
    }

    function insertZwnjAtCursor() {
        var textarea = document.getElementById('content');
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

        var isCtrlShift2 = (e.ctrlKey || e.metaKey) && e.shiftKey && e.keyCode === 50;
        var isShiftSpace = e.shiftKey && e.keyCode === 32 && !e.ctrlKey && !e.metaKey && !e.altKey;

        if (isCtrlShift2 || isShiftSpace) {
            e.preventDefault();
            insertZwnjAtCursor();
        }
    });
})();
