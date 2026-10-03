import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source = readFileSync(new URL('../../resources/js/forminator-digits.js', import.meta.url), 'utf8');

function page(body) {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, { runScripts: 'outside-only' });
    dom.window.eval(source);

    return dom.window;
}

/** Types a value into an input, as a browser does: the value, then an input event. */
function type(input, value) {
    input.value = value;
    input.dispatchEvent(new input.ownerDocument.defaultView.Event('input', { bubbles: true }));
}

const FORM = `
    <form class="forminator-custom-form">
        <div class="forminator-field-phone"><input type="text" name="phone-1" class="forminator-input forminator-field--phone"></div>
        <div class="forminator-field-number"><input type="text" name="number-1" class="forminator-input forminator-number--field"></div>
        <div class="forminator-field-currency"><input type="text" name="currency-1" class="forminator-input forminator-currency"></div>
        <div class="forminator-field-text persian-kit-national-id"><input type="text" name="text-1" class="forminator-input"></div>
        <div class="forminator-field-text"><input type="text" name="text-2" class="forminator-input"></div>
    </form>
    <input type="text" name="outside" class="forminator-field--phone">`;

test('phone, number, currency and Iranian fields get English digits as people type', () => {
    const window = page(FORM);
    const { document } = window;

    type(document.querySelector('[name="phone-1"]'), '۰۹۱۲۱۲۳۴۵۶۷');
    type(document.querySelector('[name="number-1"]'), '١٢');
    type(document.querySelector('[name="currency-1"]'), '۲۵۰۰۰');
    type(document.querySelector('[name="text-1"]'), '۰۰۱۳۵۴۲۴۱۹');

    assert.equal(document.querySelector('[name="phone-1"]').value, '09121234567');
    assert.equal(document.querySelector('[name="number-1"]').value, '12');
    assert.equal(document.querySelector('[name="currency-1"]').value, '25000');
    assert.equal(document.querySelector('[name="text-1"]').value, '0013542419');
});

test('other text and inputs outside Forminator forms keep their digits', () => {
    const window = page(FORM);
    const { document } = window;

    type(document.querySelector('[name="text-2"]'), 'پلاک ۱۲');
    type(document.querySelector('[name="outside"]'), '۱۲');

    assert.equal(document.querySelector('[name="text-2"]').value, 'پلاک ۱۲');
    assert.equal(document.querySelector('[name="outside"]').value, '۱۲');
});

test('the digits are fixed before Forminator\'s own listeners run, and the caret stays', () => {
    const window = page(FORM);
    const { document } = window;
    const input = document.querySelector('[name="phone-1"]');
    const seen = [];
    input.addEventListener('input', () => seen.push(input.value));

    input.focus();
    input.value = '۰۹۱۲۳';
    input.setSelectionRange(2, 2);
    input.dispatchEvent(new window.Event('input', { bubbles: true }));

    assert.deepEqual(seen, ['09123']);
    assert.equal(input.selectionStart, 2);
    assert.equal(input.selectionEnd, 2);
});

test('a changed or pasted value is fixed too', () => {
    const window = page(FORM);
    const { document } = window;
    const input = document.querySelector('[name="number-1"]');

    input.value = '۴۲';
    input.dispatchEvent(new window.Event('change', { bubbles: true }));

    assert.equal(input.value, '42');
    assert.equal(window.PersianKitForminatorDigits.toAsciiDigits('۱۲٣abc'), '123abc');
});

test('a typed Persian digit is swapped before a mask sees it, at the caret', () => {
    const window = page(FORM);
    const { document } = window;
    const input = document.querySelector('[name="number-1"]');
    // As Inputmask does: a digit it doesn't know is dropped.
    const seen = [];
    input.addEventListener('beforeinput', (event) => {
        seen.push(event.data);
        if (/[^0-9]/.test(event.data)) {
            event.preventDefault();
        }
    });

    input.focus();
    input.value = '1,234';
    input.setSelectionRange(1, 1);

    const typed = new window.InputEvent('beforeinput', { data: '۵', inputType: 'insertText', bubbles: true, cancelable: true });
    input.dispatchEvent(typed);

    assert.equal(typed.defaultPrevented, true);
    assert.deepEqual(seen, [], 'the mask never sees the Persian digit');
    assert.equal(input.value, '15,234');
    assert.equal(input.selectionStart, 2);
});

test('a key press with a Persian digit inserts the English one', () => {
    const window = page(FORM);
    const { document } = window;
    const input = document.querySelector('[name="phone-1"]');
    input.focus();

    const pressed = new window.KeyboardEvent('keypress', { key: '۹', bubbles: true, cancelable: true });
    input.dispatchEvent(pressed);
    const english = new window.KeyboardEvent('keypress', { key: '9', bubbles: true, cancelable: true });
    input.dispatchEvent(english);

    assert.equal(pressed.defaultPrevented, true);
    assert.equal(english.defaultPrevented, false);
    assert.equal(input.value, '9');
});

test('a pasted value with Persian digits is pasted with English ones', () => {
    const window = page(FORM);
    const { document } = window;
    const input = document.querySelector('[name="text-1"]');
    input.focus();

    const pasted = new window.InputEvent('beforeinput', { inputType: 'insertFromPaste', bubbles: true, cancelable: true });
    Object.defineProperty(pasted, 'dataTransfer', { value: { getData: () => '۰۰۱۳۵۴۲۴۱۹' } });
    input.dispatchEvent(pasted);

    assert.equal(input.value, '0013542419');

    // Other text is pasted as it is.
    const other = document.querySelector('[name="text-2"]');
    const text = new window.InputEvent('beforeinput', { data: '۱۲', inputType: 'insertText', bubbles: true, cancelable: true });
    other.dispatchEvent(text);
    assert.equal(text.defaultPrevented, false);
});
