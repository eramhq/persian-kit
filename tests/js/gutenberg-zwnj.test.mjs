import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source = readFileSync(new URL('../../resources/js/gutenberg-zwnj.js', import.meta.url), 'utf8');
const ZWNJ = '‌';

function page(body = '') {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, { runScripts: 'outside-only' });
    dom.window.eval(source);

    return dom.window;
}

/** The block editor's canvas: an iframe whose document holds the editable text. */
function addCanvas(window, text = 'سلام') {
    const iframe = window.document.createElement('iframe');
    iframe.name = 'editor-canvas';
    window.document.body.appendChild(iframe);
    const doc = iframe.contentDocument;
    doc.body.innerHTML = `<p contenteditable="true">${text}</p>`;

    return { iframe, doc, editable: doc.querySelector('p') };
}

/** The editor is up long before anyone types: let the page notice the new canvas. */
function settle(window) {
    return new Promise((resolve) => window.setTimeout(resolve, 0));
}

function place(editable, offset) {
    const doc = editable.ownerDocument;
    const range = doc.createRange();
    range.setStart(editable.firstChild, offset);
    range.collapse(true);
    const selection = doc.defaultView.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

function shiftSpace(target, extra = {}) {
    const view = target.ownerDocument.defaultView;
    const event = new view.KeyboardEvent('keydown', {
        key: ' ', code: 'Space', keyCode: 32, shiftKey: true, bubbles: true, cancelable: true, ...extra,
    });
    target.dispatchEvent(event);

    return event;
}

test('Shift+Space inserts a half-space in text inside the block editor canvas iframe', async () => {
    const window = page();
    const { editable } = addCanvas(window);
    await settle(window);
    place(editable, 2);

    const event = shiftSpace(editable);

    assert.equal(event.defaultPrevented, true);
    assert.equal(editable.textContent, 'سل' + ZWNJ + 'ام');
});

test('the iframe canvas is found when it is created after the script has loaded', async () => {
    const window = page();
    await settle(window);
    const { editable } = addCanvas(window, 'می خواهم');
    await settle(window);
    place(editable, 2);

    shiftSpace(editable);

    assert.equal(editable.textContent, 'می' + ZWNJ + ' خواهم');
});

test('one key press inserts one half-space, even when the document is bound twice', () => {
    const window = page();
    const { iframe, editable } = addCanvas(window);
    iframe.dispatchEvent(new window.Event('load'));
    window.document.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));
    iframe.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));
    place(editable, 2);

    shiftSpace(editable);

    assert.equal(editable.textContent.split(ZWNJ).length - 1, 1);
});

test('the half-space still works after the canvas document loses its listeners', () => {
    const window = page();
    const { iframe, doc } = addCanvas(window);

    // The block editor rewrites the canvas with document.open(), which drops listeners.
    doc.open();
    doc.write('<!doctype html><html><body><p contenteditable="true">متن</p></body></html>');
    doc.close();
    iframe.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));
    const editable = iframe.contentDocument.querySelector('p');
    place(editable, 1);

    shiftSpace(editable);

    assert.equal(editable.textContent, 'م' + ZWNJ + 'تن');
});

test('a key press during IME composition and other shortcuts are left alone', () => {
    const window = page();
    const { editable } = addCanvas(window);
    place(editable, 2);

    assert.equal(shiftSpace(editable, { isComposing: true }).defaultPrevented, false);
    assert.equal(shiftSpace(editable, { keyCode: 229 }).defaultPrevented, false);
    assert.equal(shiftSpace(editable, { ctrlKey: true }).defaultPrevented, false);
    assert.equal(shiftSpace(editable, { shiftKey: false }).defaultPrevented, false);
    assert.equal(editable.textContent, 'سلام');
});

test('text that is not editable in the canvas is not changed', () => {
    const window = page();
    const { doc } = addCanvas(window);
    doc.body.insertAdjacentHTML('beforeend', '<p id="plain">ثابت</p>');
    const plain = doc.getElementById('plain');

    assert.equal(shiftSpace(plain).defaultPrevented, false);
    assert.equal(plain.textContent, 'ثابت');
});

test('the half-space still works in editable text and fields of the main document', () => {
    const window = page('<div id="title" contenteditable="true">عنوان</div><textarea id="note">abc</textarea>');
    const { document } = window;

    const title = document.getElementById('title');
    place(title, 2);
    shiftSpace(title);
    assert.equal(title.textContent, 'عن' + ZWNJ + 'وان');

    const note = document.getElementById('note');
    note.selectionStart = note.selectionEnd = 1;
    shiftSpace(note);
    assert.equal(note.value, 'a' + ZWNJ + 'bc');
});

test('the half-space goes in through the editor command when the browser has one, so undo keeps working', async () => {
    const window = page();
    const { doc, editable } = addCanvas(window);
    await settle(window);
    const inserted = [];
    doc.execCommand = (command, _ui, value) => {
        inserted.push([command, value]);

        return true;
    };
    place(editable, 2);

    const event = shiftSpace(editable);

    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(inserted, [['insertText', ZWNJ]]);
    assert.equal(editable.textContent, 'سلام', 'the browser does the insert, not the script');
});
