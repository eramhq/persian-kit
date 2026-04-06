(function () {
    'use strict';

    var ZWNJ = '\u200C';

    function isShortcut(event) {
        var isCtrlShift2 = (event.ctrlKey || event.metaKey) && event.shiftKey && (event.key === '2' || event.keyCode === 50);
        var isShiftSpace = event.shiftKey && !event.ctrlKey && !event.metaKey && !event.altKey
            && (event.key === ' ' || event.code === 'Space' || event.keyCode === 32);

        return isCtrlShift2 || isShiftSpace;
    }

    function isEditableTarget(target) {
        if (!target) {
            return false;
        }

        if (target.tagName === 'TEXTAREA') {
            return true;
        }

        if (target.tagName === 'INPUT') {
            var type = (target.getAttribute('type') || 'text').toLowerCase();
            return ['text', 'search', 'url', 'tel', 'email', 'password'].indexOf(type) !== -1;
        }

        return !!target.closest('[contenteditable="true"]');
    }

    function dispatchInput(target) {
        var event = new Event('input', { bubbles: true });
        target.dispatchEvent(event);
    }

    function insertIntoField(field) {
        var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;
        var value = field.value;

        field.value = value.substring(0, start) + ZWNJ + value.substring(end);
        field.selectionStart = field.selectionEnd = start + 1;
        dispatchInput(field);
    }

    function insertIntoContentEditable(target) {
        var editable = target.closest('[contenteditable="true"]');
        if (!editable) {
            return;
        }

        var selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        var range = selection.getRangeAt(0);
        range.deleteContents();

        var textNode = document.createTextNode(ZWNJ);
        range.insertNode(textNode);

        range.setStartAfter(textNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);

        dispatchInput(editable);
    }

    document.addEventListener('keydown', function (event) {
        if (event.defaultPrevented || event.isComposing || !isShortcut(event) || !isEditableTarget(event.target)) {
            return;
        }

        event.preventDefault();

        if (event.target.tagName === 'TEXTAREA' || event.target.tagName === 'INPUT') {
            insertIntoField(event.target);
            return;
        }

        insertIntoContentEditable(event.target);
    });
})();
