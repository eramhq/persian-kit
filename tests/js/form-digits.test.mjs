import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source = readFileSync(new URL('../../resources/js/form-digits.js', import.meta.url), 'utf8');

function page(body) {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, { runScripts: 'outside-only' });
    dom.window.eval(source);

    return dom.window;
}

function type(input, value) {
    input.value = value;
    input.dispatchEvent(new input.ownerDocument.defaultView.Event('input', { bubbles: true }));
}

const FORMS = `
    <form class="one"><input name="a" class="digits"><input name="b"></form>
    <form class="two"><input name="c" class="digits"></form>`;

test('only inputs a builder watches get English digits', () => {
    const window = page(FORMS);
    const { document } = window;

    type(document.querySelector('[name="a"]'), '۱۲');
    assert.equal(document.querySelector('[name="a"]').value, '۱۲', 'nothing is watched yet');

    window.PersianKitFormDigits.watch('.one input.digits');
    type(document.querySelector('[name="a"]'), '۱۲');
    type(document.querySelector('[name="b"]'), '۱۲');
    type(document.querySelector('[name="c"]'), '۱۲');

    assert.equal(document.querySelector('[name="a"]').value, '12');
    assert.equal(document.querySelector('[name="b"]').value, '۱۲');
    assert.equal(document.querySelector('[name="c"]').value, '۱۲');
});

test('two builders on one page share the listeners', () => {
    const window = page(FORMS);
    const { document } = window;
    window.PersianKitFormDigits.watch('.one input.digits');
    window.PersianKitFormDigits.watch('.two input.digits');
    window.PersianKitFormDigits.watch('.two input.digits');

    const input = document.querySelector('[name="c"]');
    input.focus();
    const typed = new window.InputEvent('beforeinput', { data: '۷', inputType: 'insertText', bubbles: true, cancelable: true });
    input.dispatchEvent(typed);

    assert.equal(typed.defaultPrevented, true);
    assert.equal(input.value, '7', 'inserted once');
    assert.equal(window.PersianKitFormDigits.isField(document.querySelector('[name="a"]')), true);
});

test('loading the script twice keeps one set of listeners', () => {
    const window = page(FORMS);
    window.PersianKitFormDigits.watch('.one input.digits');
    window.eval(source);

    const input = window.document.querySelector('[name="a"]');
    input.focus();
    input.dispatchEvent(new window.KeyboardEvent('keypress', { key: '۳', bubbles: true, cancelable: true }));

    assert.equal(input.value, '3');
    assert.equal(window.PersianKitFormDigits.toAsciiDigits('٤۵'), '45');
});
