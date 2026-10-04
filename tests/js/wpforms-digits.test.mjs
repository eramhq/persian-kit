import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const shared = readFileSync(new URL('../../resources/js/form-digits.js', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/wpforms-digits.js', import.meta.url), 'utf8');

function page(body) {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, { runScripts: 'outside-only' });
    dom.window.eval(shared);
    dom.window.eval(source);

    return dom.window;
}

/** Types a value into an input, as a browser does: the value, then an input event. */
function type(input, value) {
    input.value = value;
    input.dispatchEvent(new input.ownerDocument.defaultView.Event('input', { bubbles: true }));
}

// As WPForms prints them: each field in a div with its type's class.
const FORM = `
    <div class="wpforms-container wpforms-container-full"><form class="wpforms-form">
        <div class="wpforms-field-container">
            <div class="wpforms-field wpforms-field-number"><input type="text" name="wpforms[fields][1]"></div>
            <div class="wpforms-field wpforms-field-payment-single"><input type="text" name="wpforms[fields][2]" class="wpforms-payment-user-input"></div>
            <div class="wpforms-field wpforms-field-persian-kit-mobile"><input type="tel" name="wpforms[fields][3]"></div>
            <div class="wpforms-field wpforms-field-persian-kit-national-id"><input type="text" name="wpforms[fields][4]"></div>
            <div class="wpforms-field wpforms-field-persian-kit-postcode"><input type="text" name="wpforms[fields][5]"></div>
            <div class="wpforms-field wpforms-field-persian-kit-card"><input type="text" name="wpforms[fields][6]"></div>
            <div class="wpforms-field wpforms-field-persian-kit-iban"><input type="text" name="wpforms[fields][7]"></div>
            <div class="wpforms-field wpforms-field-text"><input type="text" name="wpforms[fields][8]"></div>
        </div>
    </form></div>
    <div class="wpforms-field-number"><input type="text" name="outside"></div>`;

test('numbers, a price and the Iranian fields get English digits as people type', () => {
    const { document } = page(FORM);
    const values = { 1: '١٢', 2: '۲۵۰۰۰', 3: '۰۹۱۲۱۲۳۴۵۶۷', 4: '۰۰۱۳۵۴۲۴۱۹', 5: '۱۲۳۴۵۶۷۸۹۰', 6: '۶۰۳۷', 7: 'IR۱۲' };

    for (const [id, value] of Object.entries(values)) {
        type(document.querySelector(`[name="wpforms[fields][${id}]"]`), value);
    }

    assert.deepEqual(
        Object.keys(values).map((id) => document.querySelector(`[name="wpforms[fields][${id}]"]`).value),
        ['12', '25000', '09121234567', '0013542419', '1234567890', '6037', 'IR12']
    );
});

test('other text and inputs outside WPForms forms keep their digits', () => {
    const { document } = page(FORM);

    type(document.querySelector('[name="wpforms[fields][8]"]'), 'پلاک ۱۲');
    type(document.querySelector('[name="outside"]'), '۱۲');

    assert.equal(document.querySelector('[name="wpforms[fields][8]"]').value, 'پلاک ۱۲');
    assert.equal(document.querySelector('[name="outside"]').value, '۱۲');
});

test('a key press with a Persian digit inserts the English one', () => {
    const window = page(FORM);
    const input = window.document.querySelector('[name="wpforms[fields][1]"]');
    input.focus();

    const pressed = new window.KeyboardEvent('keypress', { key: '۹', bubbles: true, cancelable: true });
    input.dispatchEvent(pressed);

    assert.equal(pressed.defaultPrevented, true);
    assert.equal(input.value, '9');
});
