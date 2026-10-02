import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const jquerySource = readFileSync(require.resolve('jquery/dist/jquery.js'), 'utf8');
const pickerSource = readFileSync(new URL('../../public/js/datepicker.js', import.meta.url), 'utf8');
const fieldSource = readFileSync(new URL('../../resources/js/date-field.js', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/classic-date-fields.js', import.meta.url), 'utf8');

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** touch_time()'s fields (wp-admin/includes/template.php); $multi drops the ids. */
function touchTime({ aa = '2026', mm = '10', jj = '02', hh = '09', mn = '05', multi = false } = {}) {
    const id = (name) => (multi ? '' : `id="${name}" `);
    const options = MONTHS.map((text, i) => {
        const value = String(i + 1).padStart(2, '0');
        return `<option value="${value}" data-text="${text}"${value === mm ? ' selected' : ''}>${value}-${text}</option>`;
    }).join('');
    const input = (name, value, size) => `<label><span class="screen-reader-text">${name}</span><input type="text" ${id(name)}name="${name}" value="${value}" size="${size}" maxlength="${size}" autocomplete="off" class="form-required" inputmode="numeric" /></label>`;

    return `<div class="timestamp-wrap"><label><span class="screen-reader-text">Month</span><select class="form-required" ${id('mm')}name="mm">${options}</select></label> `
        + `${input('jj', jj, 2)}, ${input('aa', aa, 4)} at ${input('hh', hh, 2)}:${input('mn', mn, 2)}</div>`
        + `<input type="hidden" ${id('ss')}name="ss" value="00" />`;
}

/** The publish box (wp-admin/includes/meta-boxes.php). */
function publishBox(date = {}) {
    const { aa = '2026', mm = '10', jj = '02', hh = '09', mn = '05' } = date;
    const hidden = Object.entries({ aa, mm, jj, hh, mn }).map(([name, value]) => `<input type="hidden" id="hidden_${name}" name="hidden_${name}" value="${value}" />`).join('');

    return `
        <form id="post"><div id="submitdiv"><div class="misc-pub-section curtime misc-pub-curtime">
            <span id="timestamp">Published on: <b>Oct 2, 2026 at 09:05</b></span>
            <a href="#edit_timestamp" class="edit-timestamp hide-if-no-js" role="button">Edit</a>
            <fieldset id="timestampdiv" class="hide-if-js">
                <legend class="screen-reader-text">Date and time</legend>
                ${touchTime(date)}
                ${hidden}
                <p><a href="#edit_timestamp" class="save-timestamp hide-if-no-js button">OK</a>
                <a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js button-cancel">Cancel</a></p>
            </fieldset>
        </div></div></form>`;
}

/** A page with jQuery, the date picker, the field script and this script. */
async function page(body) {
    const dom = new JSDOM(`<!doctype html><html lang="fa" dir="rtl"><body>${body}</body></html>`, {
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });
    const { window } = dom;

    delete window.HTMLElement.prototype.attachInternals;
    window.persianKitDateField = { locale: 'fa-IR', labels: { time: 'ساعت' } };
    window.eval(jquerySource);
    window.eval(pickerSource);
    window.eval(fieldSource);
    window.eval(source);

    await new Promise((resolve) => window.jQuery(resolve));
    await tick(window);

    return window;
}

function tick(window) {
    return new Promise((resolve) => window.setTimeout(resolve, 0));
}

/** What post.js's updateText() writes into #timestamp. */
function updateText(window) {
    const $ = window.jQuery;
    const mm = $('#mm').val();
    $('#timestamp').html(`\nPublished on: <b>${$(`option[value="${mm}"]`, '#mm').attr('data-text')} ${parseInt($('#jj').val(), 10)}, ${$('#aa').val()} at ${$('#hh').val()}:${$('#mn').val()}</b> `);
}

/** post.js's Cancel: the fields come back from hidden_*. */
function bindCancel(window) {
    const $ = window.jQuery;
    $('#timestampdiv .cancel-timestamp').on('click', (event) => {
        for (const name of ['mm', 'jj', 'aa', 'hh', 'mn']) {
            $(`#${name}`).val($(`#hidden_${name}`).val());
        }
        updateText(window);
        event.preventDefault();
    });
}

function pickerOf(window, container) {
    return window.PersianKitDateField.picker(container.querySelector('.pk-jalali-date input[data-persian-kit-date]'));
}

test('the publish box gets one row: the picker, then WordPress\'s hour and minute', async () => {
    const window = await page(publishBox());
    const { document } = window;
    const box = document.getElementById('timestampdiv');
    const row = box.querySelector('.pk-jalali-date.timestamp-wrap');
    const picker = pickerOf(window, box);

    assert.ok(row);
    assert.equal(picker.value, '2026-10-02');
    assert.equal(picker.displayValue, '۱۴۰۵/۰۷/۱۰');
    assert.equal(picker.getAttribute('aria-label'), 'Date and time');
    assert.ok(picker.classList.contains('persian-kit-date-picker--no-hint'));

    // The hour and minute keep their ids, names and labels.
    assert.equal(row.querySelector('input[name="hh"]'), document.getElementById('hh'));
    assert.equal(row.querySelector('input[name="mn"]').closest('label').textContent, 'mn');
    assert.equal(box.querySelector('.timestamp-wrap:not(.pk-jalali-date)').style.display, 'none');
    assert.equal(document.getElementById('hh').closest('.pk-jalali-time').dir, 'ltr');
    assert.equal(row.querySelector('.pk-jalali-time').textContent, 'hh:mn');

    // The proxy is not submitted.
    assert.ok(!Object.hasOwn(Object.fromEntries(new window.FormData(document.getElementById('post'))), ''));
});

test('a picked date is written into aa, mm and jj', async () => {
    const window = await page(publishBox());
    const { document } = window;

    pickerOf(window, document.getElementById('timestampdiv')).setValue('2026-10-03');

    assert.equal(document.getElementById('aa').value, '2026');
    assert.equal(document.getElementById('mm').value, '10');
    assert.equal(document.getElementById('jj').value, '03');
    assert.equal(document.getElementById('hh').value, '09');

    // A cleared picker shows the date WordPress still has.
    pickerOf(window, document.getElementById('timestampdiv')).clear();
    assert.equal(document.getElementById('jj').value, '03');
    assert.equal(pickerOf(window, document.getElementById('timestampdiv')).value, '2026-10-03');
});

test('Cancel shows the restored date in the picker', async () => {
    const window = await page(publishBox());
    const { document } = window;
    bindCancel(window);
    const picker = pickerOf(window, document.getElementById('timestampdiv'));

    picker.setValue('2027-01-25');
    assert.equal(document.getElementById('aa').value, '2027');

    document.querySelector('.cancel-timestamp').click();
    await tick(window);

    assert.equal(document.getElementById('aa').value, '2026');
    assert.equal(picker.value, '2026-10-02');
});

test('the publish text shows the Jalali date whenever post.js rewrites it', async () => {
    const window = await page(publishBox());
    const { document } = window;
    const bold = () => document.querySelector('#timestamp b').textContent;

    assert.equal(bold(), '۱۰ مهر ۱۴۰۵ ساعت ۰۹:۰۵');

    pickerOf(window, document.getElementById('timestampdiv')).setValue('2026-10-03');
    document.getElementById('hh').value = '17';
    updateText(window);
    await tick(window);
    assert.equal(bold(), '۱۱ مهر ۱۴۰۵ ساعت ۱۷:۰۵');
    assert.match(document.getElementById('timestamp').textContent, /Published on:/);

    // "Publish immediately" has no date.
    document.getElementById('timestamp').innerHTML = 'Publish <b>immediately</b>';
    await tick(window);
    assert.equal(bold(), 'immediately');
});

test('Persian digits typed into the hour and minute become English', async () => {
    const window = await page(publishBox());
    const { document } = window;
    const hour = document.getElementById('hh');

    hour.value = '۱۴';
    hour.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(hour.value, '14');

    const minute = document.getElementById('mn');
    minute.value = '٣٠';
    minute.dispatchEvent(new window.Event('change', { bubbles: true }));
    assert.equal(minute.value, '30');
});

test('each Quick Edit row gets its own picker', async () => {
    const window = await page(`
        <table><tbody id="the-list">
            <tr id="post-7"><td><button type="button" class="button-link editinline">Quick Edit</button></td></tr>
            <tr id="post-8"><td><button type="button" class="button-link editinline">Quick Edit</button></td></tr>
        </tbody></table>
        <table><tbody>
            <tr id="inline-edit" class="inline-edit-row" style="display: none"><td>
                <fieldset class="inline-edit-date"><legend><span class="title">Date</span></legend>${touchTime({ multi: true })}</fieldset>
            </td></tr>
        </tbody></table>
        <div id="inline_7"><div class="aa">2026</div><div class="mm">10</div><div class="jj">02</div><div class="hh">09</div><div class="mn">05</div></div>
        <div id="inline_8"><div class="aa">2025</div><div class="mm">03</div><div class="jj">21</div><div class="hh">12</div><div class="mn">00</div></div>`);
    const { document, jQuery: $ } = window;
    const changed = [];

    // inlineEditPost.edit(): a fresh clone of #inline-edit, filled from the
    // post's hidden data; revert() removes the open one.
    $('#the-list').on('click', '.editinline', function () {
        $('tr.inline-editor').remove();
        const id = $(this).closest('tr').attr('id').replace('post-', '');
        const row = $('#inline-edit').clone(true).attr('id', `edit-${id}`).addClass('inline-editor').show();
        $(`#post-${id}`).after(row);
        for (const name of ['aa', 'mm', 'jj', 'hh', 'mn']) {
            $(`:input[name="${name}"]`, row).val($(`#inline_${id} .${name}`).text());
        }
        // init(): Escape (keyup) closes Quick Edit, Enter (keydown) saves it.
        row.on('keyup', (event) => {
            if (event.which === 27) {
                changed.push('closed');
            }
        });
        row.find('td').on('keydown', (event) => {
            if (event.which === 13) {
                changed.push('saved');
            }
        });
    });

    document.querySelector('#post-7 .editinline').click();
    await tick(window);

    const row7 = document.getElementById('edit-7');
    assert.equal(pickerOf(window, row7).value, '2026-10-02');
    assert.equal(pickerOf(window, row7).getAttribute('aria-label'), 'Date');
    // The template is left alone.
    assert.equal(document.querySelectorAll('#inline-edit intl-datepicker').length, 0);

    pickerOf(window, row7).setValue('2026-10-03');
    assert.equal(row7.querySelector('[name="jj"]').value, '03');

    // With the calendar open, Enter and Escape are the calendar's. The
    // picker closes itself on either before the key leaves it.
    const picker7 = pickerOf(window, row7);
    const closesItself = (event) => {
        if (event.key === 'Escape' || event.key === 'Enter') {
            picker7.dispatchEvent(new window.CustomEvent('intl-close'));
        }
    };
    picker7.addEventListener('keydown', closesItself);
    const press = (key, which) => {
        picker7.dispatchEvent(new window.KeyboardEvent('keydown', { key, keyCode: which, which, bubbles: true }));
        picker7.dispatchEvent(new window.KeyboardEvent('keyup', { key, keyCode: which, which, bubbles: true }));
    };
    picker7.dispatchEvent(new window.CustomEvent('intl-open'));
    press('Escape', 27);
    picker7.dispatchEvent(new window.CustomEvent('intl-open'));
    press('Enter', 13);
    assert.deepEqual(changed, []);

    // From its text field the picker leaves Escape to a document listener,
    // which no longer gets it; the script closes the calendar itself.
    picker7.removeEventListener('keydown', closesItself);
    let closed = 0;
    picker7.close = () => {
        closed++;
        picker7.dispatchEvent(new window.CustomEvent('intl-close'));
    };
    picker7.dispatchEvent(new window.CustomEvent('intl-open'));
    picker7.shadowRoot.querySelector('input').dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', keyCode: 27, which: 27, bubbles: true, composed: true }));
    assert.equal(closed, 1);
    assert.deepEqual(changed, []);
    picker7.addEventListener('keydown', closesItself);

    // With the calendar closed, Quick Edit gets them.
    press('Enter', 13);
    press('Escape', 27);
    assert.deepEqual(changed, ['saved', 'closed']);

    document.querySelector('#post-8 .editinline').click();
    await tick(window);

    const row8 = document.getElementById('edit-8');
    assert.equal(document.getElementById('edit-7'), null);
    assert.equal(row8.querySelectorAll('intl-datepicker').length, 1);
    assert.equal(pickerOf(window, row8).value, '2025-03-21');
    assert.equal(pickerOf(window, row8).displayValue, '۱۴۰۴/۰۱/۰۱');

    // What inlineEditPost.save() posts.
    assert.deepEqual(Object.fromEntries(new window.URLSearchParams($(':input', row8).serialize())), { mm: '03', jj: '21', aa: '2025', hh: '12', mn: '00', ss: '00' });
});
