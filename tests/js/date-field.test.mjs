import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const pickerSource = readFileSync(new URL('../../public/js/datepicker.js', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/date-field.js', import.meta.url), 'utf8');

/**
 * A page with the built date picker and the adapter. Without
 * attachInternals, as in Safari before 16.4: the picker takes no part in
 * the form, so only the original input can submit the date. (jsdom's
 * ElementInternals also lacks setFormValue.)
 */
async function page(body) {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, {
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });
    const { window } = dom;

    delete window.HTMLElement.prototype.attachInternals;
    window.persianKitDateField = { locale: 'fa-IR', labels: { time: 'ساعت' } };
    window.eval(pickerSource);
    window.eval(source);

    if (window.document.readyState === 'loading') {
        await new Promise((resolve) => window.document.addEventListener('DOMContentLoaded', resolve));
    }

    return window;
}

function tick(window) {
    return new Promise((resolve) => window.setTimeout(resolve, 0));
}

function submitted(form) {
    return Object.fromEntries(new form.ownerDocument.defaultView.FormData(form));
}

test('the input becomes the hidden field that submits the ISO date', async () => {
    const window = await page(`
        <form>
            <label for="visit">Visit</label>
            <input type="text" id="visit" name="visit" class="wpcf7-date" data-persian-kit-date value="2026-03-21" required>
        </form>`);
    const { document } = window;
    const input = document.getElementById('visit');
    const picker = document.querySelector('intl-datepicker');

    assert.equal(input.type, 'hidden');
    assert.equal(input.name, 'visit');
    assert.equal(input.className, 'wpcf7-date');
    assert.ok(input.hasAttribute('required'));
    assert.equal(input.previousElementSibling.firstElementChild, picker);

    assert.equal(picker.getAttribute('calendar'), 'persian');
    assert.equal(picker.getAttribute('locale'), 'fa-IR');
    assert.ok(picker.hasAttribute('required'));
    assert.ok(!picker.hasAttribute('name'));
    assert.equal(picker.displayValue, '۱۴۰۵/۰۱/۰۱');

    // The label names the picker; the input keeps its id.
    assert.equal(picker.id, 'visit-picker');
    assert.equal(document.querySelector('label').htmlFor, 'visit-picker');

    assert.deepEqual(submitted(document.querySelector('form')), { visit: '2026-03-21' });
});

test('a picked date is written into the input, with input and change events', async () => {
    const window = await page('<form><input name="visit" data-persian-kit-date></form>');
    const { document } = window;
    const input = document.querySelector('input[name="visit"]');
    const events = [];
    input.addEventListener('input', () => events.push('input'));
    input.addEventListener('change', () => events.push('change'));

    document.querySelector('intl-datepicker').setValue('2026-10-02');

    assert.equal(input.value, '2026-10-02');
    assert.deepEqual(events, ['input', 'change']);
    assert.equal(document.querySelector('intl-datepicker').displayValue, '۱۴۰۵/۰۷/۱۰');

    document.querySelector('intl-datepicker').clear();
    assert.equal(input.value, '');
});

test('only marked inputs are upgraded', async () => {
    const window = await page(`
        <form>
            <input type="date" name="plain" value="2026-03-21">
            <input name="jalali" data-persian-kit-date>
        </form>`);
    const { document } = window;

    assert.equal(document.querySelectorAll('intl-datepicker').length, 1);
    assert.equal(document.querySelector('input[name="plain"]').type, 'date');
    assert.equal(document.querySelector('input[name="jalali"]').type, 'hidden');
});

test('ACF dates are kept as Ymd', async () => {
    const window = await page(`
        <form>
            <label for="acf-field_1">Event</label>
            <input type="hidden" id="acf-field_1" name="acf[field_1]" data-persian-kit-date data-persian-kit-date-format="Ymd" value="20260321">
        </form>`);
    const { document } = window;
    const input = document.querySelector('input');
    const picker = document.querySelector('intl-datepicker');

    assert.equal(picker.value, '2026-03-21');
    assert.equal(document.querySelector('label').htmlFor, 'acf-field_1-picker');

    picker.setValue('2026-10-02');
    assert.equal(input.value, '20261002');
});

test('date and time fields add a time input and keep Y-m-d H:i:s', async () => {
    const window = await page('<form><input name="acf[field_2]" data-persian-kit-date data-persian-kit-date-format="Y-m-d H:i:s" value="2026-03-21 08:15:00"></form>');
    const { document } = window;
    const input = document.querySelector('input[name="acf[field_2]"]');
    const time = document.querySelector('input.persian-kit-date-time');

    assert.equal(time.type, 'time');
    assert.equal(time.value, '08:15');
    assert.equal(time.getAttribute('aria-label'), 'ساعت');

    document.querySelector('intl-datepicker').setValue('2026-10-02');
    assert.equal(input.value, '2026-10-02 08:15:00');

    time.value = '17:40';
    time.dispatchEvent(new window.Event('change', { bubbles: true }));
    assert.equal(input.value, '2026-10-02 17:40:00');
});

test('limits come from data attributes or the input itself', async () => {
    const window = await page(`
        <form>
            <input name="a" data-persian-kit-date min="2026-01-01" max="2026-12-31">
            <input name="b" data-persian-kit-date data-persian-kit-date-min="2026-05-01" data-persian-kit-date-disable-past data-persian-kit-date-type="range" disabled placeholder="تاریخ">
        </form>`);
    const [a, b] = window.document.querySelectorAll('intl-datepicker');

    assert.equal(a.getAttribute('min'), '2026-01-01');
    assert.equal(a.getAttribute('max'), '2026-12-31');
    assert.ok(!a.hasAttribute('type'));

    assert.equal(b.getAttribute('min'), '2026-05-01');
    assert.ok(b.hasAttribute('disable-past'));
    assert.equal(b.getAttribute('type'), 'range');
    assert.ok(b.hasAttribute('disabled'));
    assert.equal(b.getAttribute('placeholder'), 'تاریخ');
});

test('range pickers submit their own value', async () => {
    const window = await page('<form><input name="stay" data-persian-kit-date data-persian-kit-date-type="range"></form>');
    const { document } = window;

    document.querySelector('intl-datepicker').setValue('2026-10-02/2026-10-05');

    assert.equal(document.querySelector('input[name="stay"]').value, '2026-10-02/2026-10-05');
});

test('inputs added later are upgraded, and a cloned row gets its own picker', async () => {
    const window = await page('<form><div class="row"><input name="row[0]" data-persian-kit-date value="2026-03-21"></div></form>');
    const { document } = window;
    const form = document.querySelector('form');

    // As an ACF repeater adds a row: a copy of an upgraded row, renamed.
    const copy = document.querySelector('.row').cloneNode(true);
    copy.querySelector('input[name="row[0]"]').name = 'row[1]';
    form.appendChild(copy);
    await tick(window);

    assert.equal(document.querySelectorAll('.row').length, 2);
    assert.equal(copy.querySelectorAll('intl-datepicker').length, 1);

    copy.querySelector('intl-datepicker').setValue('2026-10-02');
    assert.equal(copy.querySelector('input[name="row[1]"]').value, '2026-10-02');
    assert.equal(document.querySelector('input[name="row[0]"]').value, '2026-03-21');

    const added = document.createElement('input');
    added.name = 'later';
    added.setAttribute('data-persian-kit-date', '');
    form.appendChild(added);
    await tick(window);

    assert.equal(added.type, 'hidden');
    assert.equal(document.querySelectorAll('intl-datepicker').length, 3);
});

test('a form reset restores the first value in the picker and the input', async () => {
    const window = await page('<form><input name="visit" data-persian-kit-date value="2026-03-21"><input name="empty" data-persian-kit-date></form>');
    const { document } = window;
    const [visit, empty] = document.querySelectorAll('intl-datepicker');

    visit.setValue('2026-10-02');
    empty.setValue('2026-10-03');
    document.querySelector('form').reset();
    await tick(window);

    assert.equal(document.querySelector('input[name="visit"]').value, '2026-03-21');
    assert.equal(visit.value, '2026-03-21');
    assert.equal(document.querySelector('input[name="empty"]').value, '');
    assert.equal(empty.value, '');
});

test('values in other formats are left in the input until a date is picked', async () => {
    const window = await page('<form><input name="visit" data-persian-kit-date value="۱۴۰۵/۰۷/۱۰"></form>');
    const { document } = window;

    // The server converts a typed Jalali date (DateInputParser).
    assert.equal(document.querySelector('intl-datepicker').value, '');
    assert.equal(document.querySelector('input[name="visit"]').value, '۱۴۰۵/۰۷/۱۰');
});
