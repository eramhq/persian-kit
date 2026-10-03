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

test('a Jalali value in the field is shown, and kept until a date is picked', async () => {
    const window = await page(`
        <form>
            <input name="a" data-persian-kit-date value="1405-07-10">
            <input name="b" data-persian-kit-date value="۱۴۰۵/۰۷/۱۰">
            <input name="c" data-persian-kit-date data-persian-kit-date-format="Ymd" value="14050710">
            <input name="d" data-persian-kit-date data-persian-kit-date-format="Y-m-d H:i:s" value="١٤٠٥-٠٧-١٠ ٠٨:١٥:٠٠">
        </form>`);
    const { document } = window;
    const pickers = [...document.querySelectorAll('intl-datepicker')];

    assert.deepEqual(pickers.map((picker) => picker.value), ['2026-10-02', '2026-10-02', '2026-10-02', '2026-10-02']);
    assert.equal(pickers[0].displayValue, '۱۴۰۵/۰۷/۱۰');
    assert.equal(document.querySelector('input.persian-kit-date-time').value, '08:15');

    // The server converts a value nobody changed (DateInputParser).
    assert.equal(document.querySelector('input[name="b"]').value, '۱۴۰۵/۰۷/۱۰');

    pickers[2].setValue('2026-10-03');
    assert.equal(document.querySelector('input[name="c"]').value, '20261003');
});

test('values in other formats are left in the input', async () => {
    const window = await page(`
        <form>
            <input name="visit" data-persian-kit-date value="next Friday">
            <input name="nonday" data-persian-kit-date value="1405-12-31">
        </form>`);
    const { document } = window;

    // 1405 is not a leap year: Esfand has 29 days.
    assert.deepEqual([...document.querySelectorAll('intl-datepicker')].map((picker) => picker.value), ['', '']);
    assert.equal(document.querySelector('input[name="visit"]').value, 'next Friday');
});

test('refresh() reads a value a script wrote, without events', async () => {
    const window = await page(`
        <form>
            <input name="from" data-persian-kit-date value="2026-03-21">
            <input name="at" data-persian-kit-date data-persian-kit-date-format="Y-m-d H:i:s" value="2026-03-21 08:15:00">
        </form>`);
    const { document, PersianKitDateField } = window;
    const from = document.querySelector('input[name="from"]');
    const at = document.querySelector('input[name="at"]');
    const events = [];
    document.querySelector('form').addEventListener('change', (event) => events.push(event.target.name));
    document.querySelector('form').addEventListener('input', (event) => events.push(event.target.name));

    // As jQuery's .val('') does: no events.
    from.value = '';
    PersianKitDateField.refresh(from);
    assert.equal(PersianKitDateField.picker(from).value, '');
    assert.equal(from.value, '');

    // The value the picker held before is shown again.
    PersianKitDateField.picker(from).setValue('2026-10-02');
    events.length = 0;
    from.value = '2026-03-21';
    PersianKitDateField.refresh(from);
    assert.equal(PersianKitDateField.picker(from).value, '2026-03-21');

    // A Jalali value stays in the field.
    from.value = '1405-07-10';
    PersianKitDateField.refresh(from);
    assert.equal(PersianKitDateField.picker(from).value, '2026-10-02');
    assert.equal(from.value, '1405-07-10');

    at.value = '2026-10-02 17:40:00';
    PersianKitDateField.refresh(at);
    assert.equal(PersianKitDateField.picker(at).value, '2026-10-02');
    assert.equal(document.querySelector('input.persian-kit-date-time').value, '17:40');

    assert.deepEqual(events, []);

    // Picking still writes the field.
    PersianKitDateField.picker(from).setValue('2026-10-05');
    assert.equal(from.value, '2026-10-05');
    assert.deepEqual(events, ['from', 'from']);
});

test('picker() returns the field\'s picker', async () => {
    const window = await page('<form><input name="a" data-persian-kit-date><input name="b" data-persian-kit-date><input name="plain"></form>');
    const { document, PersianKitDateField } = window;
    const [a, b] = document.querySelectorAll('intl-datepicker');

    assert.equal(PersianKitDateField.picker(document.querySelector('input[name="a"]')), a);
    assert.equal(PersianKitDateField.picker(document.querySelector('input[name="b"]')), b);
    assert.equal(PersianKitDateField.picker(document.querySelector('input[name="plain"]')), null);
});

test('hint="off" marks the picker to hide its typing hint', async () => {
    const window = await page('<form><input name="a" data-persian-kit-date data-persian-kit-date-hint="off"><input name="b" data-persian-kit-date></form>');
    const [a, b] = window.document.querySelectorAll('intl-datepicker');

    assert.ok(a.classList.contains('persian-kit-date-picker--no-hint'));
    assert.ok(!b.classList.contains('persian-kit-date-picker--no-hint'));
    assert.ok(a.hasAttribute('allow-input'));
});

test('day-month-year formats are written and read in the field\'s order', async () => {
    const window = await page(`
        <form>
            <input name="a" data-persian-kit-date data-persian-kit-date-format="d/m/Y">
            <input name="b" data-persian-kit-date data-persian-kit-date-format="m/d/Y">
            <input name="c" data-persian-kit-date data-persian-kit-date-format="Y.m.d">
        </form>`);
    const { document } = window;
    const pickers = [...document.querySelectorAll('intl-datepicker')];

    pickers.forEach((picker) => picker.setValue('2026-10-02'));

    assert.deepEqual(submitted(document.querySelector('form')), { a: '02/10/2026', b: '10/02/2026', c: '2026.10.02' });
    assert.equal(window.PersianKitDateField.serialize('2026-10-02', '', 'd-m-Y'), '02-10-2026');
});

test('a value in a day-month-year format is shown: a default date, a draft, a prefill', async () => {
    const window = await page(`
        <form>
            <input name="a" data-persian-kit-date data-persian-kit-date-format="d/m/Y" value="02/10/2026">
            <input name="b" data-persian-kit-date data-persian-kit-date-format="m/d/Y" value="10/02/2026">
            <input name="c" data-persian-kit-date data-persian-kit-date-format="Y.m.d" value="2026.10.02">
            <input name="d" data-persian-kit-date data-persian-kit-date-format="d/m/Y" value="۱۰/۰۷/۱۴۰۵">
            <input name="e" data-persian-kit-date data-persian-kit-date-format="m/d/Y" value="1405/7/10">
            <input name="f" data-persian-kit-date data-persian-kit-date-format="d/m/Y" value="31/02/2026">
        </form>`);
    const { document } = window;

    assert.deepEqual(
        [...document.querySelectorAll('intl-datepicker')].map((picker) => picker.value),
        ['2026-10-02', '2026-10-02', '2026-10-02', '2026-10-02', '2026-10-02', '']
    );
    assert.equal(window.PersianKitDateField.parse('2/9/2026', 'd/m/Y').date, '2026-09-02');
    assert.equal(window.PersianKitDateField.parse('02/10/26', 'd/m/Y'), null);
});

test('formats the field does not know fall back to Y-m-d', async () => {
    const window = await page('<form><input name="a" data-persian-kit-date data-persian-kit-date-format="d/d/Y" value="2026-10-02"></form>');
    const { document } = window;

    document.querySelector('intl-datepicker').setValue('2026-10-03');
    assert.equal(document.querySelector('input[name="a"]').value, '2026-10-03');
});

test('a form reset restores a day-month-year value', async () => {
    const window = await page('<form><input name="a" data-persian-kit-date data-persian-kit-date-format="d/m/Y" value="02/10/2026"></form>');
    const { document } = window;
    const picker = document.querySelector('intl-datepicker');

    picker.setValue('2026-10-05');
    document.querySelector('form').reset();
    await tick(window);

    assert.equal(document.querySelector('input[name="a"]').value, '02/10/2026');
    assert.equal(picker.value, '2026-10-02');
});

test('a Forminator group row copied as HTML gets its own picker, value and label', async () => {
    const window = await page(`
        <form>
            <div class="group">
                <div class="row">
                    <label for="forminator-field-date-1-picker">Visit</label>
                    <input type="text" id="forminator-field-date-1-picker" name="date-1" class="forminator-datepicker" data-persian-kit-date data-persian-kit-date-format="d/m/Y" value="02/10/2026">
                </div>
            </div>
        </form>`);
    const { document } = window;
    const first = document.querySelector('.row');
    first.querySelector('intl-datepicker').setValue('2026-10-05');
    assert.equal(document.querySelector('input[name="date-1"]').value, '05/10/2026');

    // As Forminator adds a row: the row's HTML, with a suffix on every id,
    // name and for.
    const copy = document.createElement('div');
    copy.className = 'row';
    copy.innerHTML = first.innerHTML.replace(/(id=|name=|for=)"([^"]+?)"/g, '$1"$2-x7"');
    document.querySelector('.group').appendChild(copy);
    await tick(window);

    const input = copy.querySelector('input[name="date-1-x7"]');
    const pickers = copy.querySelectorAll('intl-datepicker');
    assert.equal(pickers.length, 1);
    assert.equal(input.type, 'hidden');

    // The copy starts from the value the field was rendered with.
    assert.equal(input.value, '02/10/2026');
    assert.equal(pickers[0].value, '2026-10-02');
    assert.equal(pickers[0].id, 'forminator-field-date-1-picker-x7-picker');
    assert.equal(copy.querySelector('label').htmlFor, pickers[0].id);
    assert.equal(first.querySelector('label').htmlFor, 'forminator-field-date-1-picker-picker');

    pickers[0].setValue('2026-10-09');
    assert.equal(input.value, '09/10/2026');
    assert.equal(document.querySelector('input[name="date-1"]').value, '05/10/2026');
});
