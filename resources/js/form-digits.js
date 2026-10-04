/**
 * Persian Kit — English digits in form builders' number-like inputs.
 *
 * Form builders check phone numbers and numbers in the browser, and their
 * input masks drop digits they don't know. Each builder's script names its
 * inputs with PersianKitFormDigits.watch(selector), such as
 * '.forminator-custom-form input.forminator-field--phone'. As people type or
 * paste into them, Persian and Arabic digits become English digits. Other
 * text keeps its digits.
 *
 * Masks (Inputmask, IMask, maskedinput) drop a digit they don't know before
 * the input changes. So a typed or pasted Persian digit is replaced with the
 * English one before the field sees it (keypress and beforeinput, in the
 * capture phase). Values written another way (autofill, scripts) are fixed
 * on input and change, keeping the caret where it was.
 *
 * Inputs of type number drop Persian digits before any script sees them in
 * some browsers; the server fixes the digits of a form sent without this
 * script.
 */
(function (window, document) {
    'use strict';

    if (window.PersianKitFormDigits) {
        return;
    }

    var DIGIT = /[۰-۹٠-٩]/;
    var DIGITS = /[۰-۹٠-٩]/g;

    /** Selectors the builders' scripts watch. */
    var selectors = [];

    function toAsciiDigits(value) {
        return String(value).replace(DIGITS, function (digit) {
            var code = digit.charCodeAt(0);
            return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
        });
    }

    function isField(input) {
        if (!input || !input.matches) {
            return false;
        }

        for (var i = 0; i < selectors.length; i++) {
            if (input.matches(selectors[i])) {
                return true;
            }
        }

        return false;
    }

    function selection(input) {
        try {
            return { start: input.selectionStart, end: input.selectionEnd };
        } catch (error) {
            // Inputs without a selection (type number).
            return { start: null, end: null };
        }
    }

    /** One digit for another: the caret and selection keep their place. */
    function fix(input) {
        var value = input.value;
        var fixed = toAsciiDigits(value);
        if (fixed === value) {
            return;
        }

        var range = selection(input);
        input.value = fixed;

        if (range.start !== null && document.activeElement === input) {
            try {
                input.setSelectionRange(range.start, range.end);
            } catch (error) {
                // As above.
            }
        }
    }

    /**
     * Types the text into the input in place of what the browser was about
     * to insert, as typing does, so masks and the builder's checks see it.
     */
    function insert(input, text) {
        if (typeof document.execCommand === 'function' && document.execCommand('insertText', false, text)) {
            return;
        }

        var range = selection(input);
        if (range.start === null || typeof input.setRangeText !== 'function') {
            input.value += text;
        } else {
            input.setRangeText(text, range.start, range.end, 'end');
        }
        input.dispatchEvent(new window.Event('input', { bubbles: true }));
    }

    /** A Persian digit about to be typed or pasted: insert the English one. */
    function swap(event, text) {
        var input = event.target;
        if (!text || !DIGIT.test(text) || !isField(input)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        insert(input, toAsciiDigits(text));
    }

    document.addEventListener('keypress', function (event) {
        if (!event.isComposing) {
            swap(event, event.key);
        }
    }, true);

    document.addEventListener('beforeinput', function (event) {
        var type = event.inputType || '';
        if (type.indexOf('insert') !== 0 || event.isComposing) {
            return;
        }

        var text = event.data;
        if (text === null && event.dataTransfer) {
            text = event.dataTransfer.getData('text/plain');
        }
        swap(event, text);
    }, true);

    function onChange(event) {
        if (isField(event.target)) {
            fix(event.target);
        }
    }

    document.addEventListener('input', onChange, true);
    document.addEventListener('change', onChange, true);

    window.PersianKitFormDigits = {
        /** Adds inputs, by a selector that includes their form. */
        watch: function (selector) {
            if (selector && selectors.indexOf(selector) === -1) {
                selectors.push(selector);
            }
        },
        isField: isField,
        toAsciiDigits: toAsciiDigits,
        fix: fix,
    };
})(window, document);
