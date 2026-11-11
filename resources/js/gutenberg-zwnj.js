(function () {
    'use strict';

    var ZWNJ = '‌';
    var CANVAS_SELECTOR = 'iframe[name="editor-canvas"]';

    function isShortcut(event) {
        var isShiftSpace = event.shiftKey && !event.ctrlKey && !event.metaKey && !event.altKey
            && (event.key === ' ' || event.code === 'Space' || event.keyCode === 32);

        return isShiftSpace;
    }

    function isComposing(event) {
        // 229 is what browsers report for a key press that belongs to an IME.
        return event.isComposing || event.keyCode === 229;
    }

    function isEditableTarget(target) {
        if (!target || typeof target.closest !== 'function') {
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

    function getSelectionEditableTarget(doc) {
        var view = doc.defaultView;
        var selection = view && view.getSelection ? view.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            return null;
        }

        var node = selection.anchorNode;
        if (!node) {
            return null;
        }

        // 3 is Node.TEXT_NODE; the constant of the page's own window is not the canvas's.
        if (node.nodeType === 3) {
            node = node.parentElement;
        }

        if (!node || typeof node.closest !== 'function') {
            return null;
        }

        return node.closest('[contenteditable="true"]');
    }

    function getEditableTarget(doc, target) {
        if (isEditableTarget(target)) {
            return target;
        }

        if (isEditableTarget(doc.activeElement)) {
            return doc.activeElement;
        }

        return getSelectionEditableTarget(doc);
    }

    function dispatchInput(doc, target) {
        var view = doc.defaultView || window;
        target.dispatchEvent(new view.Event('input', { bubbles: true }));
    }

    function insertIntoField(doc, field) {
        var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;
        var value = field.value;

        field.value = value.substring(0, start) + ZWNJ + value.substring(end);
        field.selectionStart = field.selectionEnd = start + 1;
        dispatchInput(doc, field);
    }

    function insertIntoContentEditable(doc, target) {
        var editable = target.closest('[contenteditable="true"]');
        if (!editable) {
            return;
        }

        // Typing through the browser keeps the editor's own undo and its
        // text state in step, which editing the page by hand does not.
        if (typeof doc.execCommand === 'function' && doc.execCommand('insertText', false, ZWNJ)) {
            return;
        }

        var view = doc.defaultView;
        var selection = view && view.getSelection ? view.getSelection() : null;
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        var range = selection.getRangeAt(0);
        range.deleteContents();

        var textNode = doc.createTextNode(ZWNJ);
        range.insertNode(textNode);

        range.setStartAfter(textNode);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);

        dispatchInput(doc, editable);
    }

    function onKeydown(event) {
        var doc = event.currentTarget && event.currentTarget.nodeType === 9
            ? event.currentTarget
            : (event.target && event.target.ownerDocument) || document;
        var editableTarget = getEditableTarget(doc, event.target);

        if (event.defaultPrevented || isComposing(event) || !isShortcut(event) || !editableTarget) {
            return;
        }

        event.preventDefault();

        if (editableTarget.tagName === 'TEXTAREA' || editableTarget.tagName === 'INPUT') {
            insertIntoField(doc, editableTarget);
            return;
        }

        insertIntoContentEditable(doc, editableTarget);
    }

    // Adding the same listener to a document twice does nothing, so these can
    // be called as often as needed without a key press being handled twice.
    function bindDocument(doc) {
        if (doc && typeof doc.addEventListener === 'function') {
            doc.addEventListener('keydown', onKeydown, true);
        }
    }

    function onCanvasLoad(event) {
        bindCanvas(event.target);
    }

    function bindCanvas(iframe) {
        if (!iframe || iframe.tagName !== 'IFRAME') {
            return;
        }

        iframe.addEventListener('load', onCanvasLoad);

        try {
            // The canvas is the same origin as the editor; guard anyway.
            bindDocument(iframe.contentDocument);
        } catch (error) {
            // Not reachable: leave it alone.
        }
    }

    function bindCanvasesIn(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }

        if (typeof root.matches === 'function' && root.matches(CANVAS_SELECTOR)) {
            bindCanvas(root);
        }

        var canvases = root.querySelectorAll(CANVAS_SELECTOR);
        for (var i = 0; i < canvases.length; i++) {
            bindCanvas(canvases[i]);
        }
    }

    // The editor text lives in an iframe, whose key presses never reach this page.
    bindDocument(document);
    bindCanvasesIn(document);

    if (typeof MutationObserver === 'function' && document.documentElement) {
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    bindCanvasesIn(added[j]);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    // The editor rewrites the canvas document when it starts, which removes
    // listeners from it. Focus moving into the canvas is the moment to add them again.
    document.addEventListener('focusin', function (event) {
        if (event.target && event.target.tagName === 'IFRAME' && event.target.matches(CANVAS_SELECTOR)) {
            bindCanvas(event.target);
        }
    }, true);
})();
