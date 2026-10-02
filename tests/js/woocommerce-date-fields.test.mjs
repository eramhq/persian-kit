import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const jquerySource = readFileSync(require.resolve('jquery/dist/jquery.js'), 'utf8');
const pickerSource = readFileSync(new URL('../../public/js/datepicker.js', import.meta.url), 'utf8');
const fieldSource = readFileSync(new URL('../../resources/js/date-field.js', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/woocommerce-date-fields.js', import.meta.url), 'utf8');

const PATTERN = '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])';

// The fields as WooCommerce 10.6.2 renders them (includes/admin/meta-boxes).
function saleDates({ from = '', to = '' } = {}) {
    return `
        <div class="options_group pricing">
            <p class="form-field sale_price_dates_fields">
                <label for="_sale_price_dates_from">Sale price dates</label>
                <input type="text" class="short" name="_sale_price_dates_from" id="_sale_price_dates_from" value="${from}" placeholder="From&hellip; YYYY-MM-DD" maxlength="10" pattern="${PATTERN}" />
                <input type="text" class="short" name="_sale_price_dates_to" id="_sale_price_dates_to" value="${to}" placeholder="To&hellip;  YYYY-MM-DD" maxlength="10" pattern="${PATTERN}" />
                <a href="#" class="description cancel_sale_schedule">Cancel</a>
            </p>
        </div>`;
}

function variation(loop, { from = '', to = '' } = {}) {
    return `
        <div class="woocommerce_variation wc-metabox">
            <div class="woocommerce_variable_attributes wc-metabox-content">
                <p class="form-row form-row-last">
                    <label>Sale price <a href="#" class="sale_schedule">Schedule</a><a href="#" class="cancel_sale_schedule hidden">Cancel schedule</a></label>
                    <input type="text" name="variable_sale_price[${loop}]" value="" />
                </p>
                <div class="form-field sale_price_dates_fields hidden">
                    <p class="form-row form-row-first">
                        <label>Sale start date</label>
                        <input type="text" class="sale_price_dates_from" name="variable_sale_price_dates_from[${loop}]" value="${from}" placeholder="From&hellip; YYYY-MM-DD" maxlength="10" pattern="${PATTERN}" />
                    </p>
                    <p class="form-row form-row-last">
                        <label>Sale end date</label>
                        <input type="text" class="sale_price_dates_to" name="variable_sale_price_dates_to[${loop}]" value="${to}" placeholder="To&hellip;  YYYY-MM-DD" maxlength="10" pattern="${PATTERN}" />
                    </p>
                </div>
            </div>
        </div>`;
}

const COUPON = `
    <p class="form-field expiry_date_field">
        <label for="expiry_date">Coupon expiry date</label>
        <input type="text" class="date-picker" style="" name="expiry_date" id="expiry_date" value="2026-12-31" placeholder="YYYY-MM-DD" pattern="${PATTERN}" />
    </p>`;

function orderDate({ date = '2026-10-02', hour = '14', minute = '05' } = {}) {
    return `
        <p class="form-field form-field-wide">
            <label for="order_date">Date created:</label>
            <input type="text" class="date-picker" name="order_date" maxlength="10" value="${date}" pattern="${PATTERN}" />@
            &lrm;
            <input type="number" class="hour" placeholder="h" name="order_date_hour" min="0" max="23" step="1" value="${hour}" pattern="([01]?[0-9]{1}|2[0-3]{1})" />:
            <input type="number" class="minute" placeholder="m" name="order_date_minute" min="0" max="59" step="1" value="${minute}" pattern="[0-5]{1}[0-9]{1}" />
            <input type="hidden" name="order_date_second" value="00" />
        </p>`;
}

function downloadPermission(loop) {
    return `
        <div class="wc-metabox closed">
            <table cellpadding="0" cellspacing="0" class="wc-metabox-content"><tbody><tr>
                <td>
                    <label>Access expires</label>
                    <input type="text" class="short date-picker" name="access_expires[${loop}]" value="" maxlength="10" placeholder="Never" pattern="${PATTERN}" />
                </td>
            </tr></tbody></table>
        </div>`;
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

    if (window.document.readyState === 'loading') {
        await new Promise((resolve) => window.document.addEventListener('DOMContentLoaded', resolve));
    }
    await tick(window);

    return window;
}

function tick(window) {
    return new Promise((resolve) => window.setTimeout(resolve, 0));
}

function pickerOf(window, input) {
    return window.PersianKitDateField.picker(input);
}

test('every WooCommerce date field gets a picker, without the YYYY-MM-DD placeholder', async () => {
    const window = await page(`
        <form id="post">
            <div id="woocommerce-product-data">${saleDates({ from: '2026-10-02', to: '2026-10-10' })}
                <div id="variable_product_options"><div class="woocommerce_variations">${variation(0, { from: '2026-11-01' })}</div></div>
            </div>
            ${COUPON}
            ${orderDate()}
            <div class="order_download_permissions"><div class="wc-metaboxes">${downloadPermission(0)}</div></div>
        </form>`);
    const { document } = window;

    const names = ['_sale_price_dates_from', '_sale_price_dates_to', 'variable_sale_price_dates_from[0]', 'variable_sale_price_dates_to[0]', 'expiry_date', 'order_date', 'access_expires[0]'];
    for (const name of names) {
        const input = document.querySelector(`input[name="${name}"]`);
        assert.equal(input.type, 'hidden', name);
        assert.ok(pickerOf(window, input), name);
        assert.equal(input.getAttribute('data-persian-kit-date-hint'), 'off', name);
    }
    assert.equal(document.querySelectorAll('intl-datepicker').length, names.length);

    const placeholder = (name) => pickerOf(window, document.querySelector(`input[name="${name}"]`)).getAttribute('placeholder');
    assert.equal(placeholder('_sale_price_dates_from'), 'From…');
    assert.equal(placeholder('_sale_price_dates_to'), 'To…');
    assert.equal(placeholder('expiry_date'), null);
    assert.equal(placeholder('access_expires[0]'), 'Never');

    // The coupon's label names its picker.
    assert.equal(document.querySelector('label[for="expiry_date-picker"]').textContent, 'Coupon expiry date');
    assert.equal(pickerOf(window, document.getElementById('expiry_date')).displayValue, '۱۴۰۵/۱۰/۱۰');

    // The hour and minute keep their own fields.
    assert.ok(!pickerOf(window, document.querySelector('input[name="order_date_hour"]')));
});

test('the sale dates limit each other', async () => {
    const window = await page(`<div id="woocommerce-product-data">${saleDates({ to: '2026-10-20' })}</div>`);
    const { document } = window;
    const from = pickerOf(window, document.getElementById('_sale_price_dates_from'));
    const to = pickerOf(window, document.getElementById('_sale_price_dates_to'));

    assert.equal(from.getAttribute('max'), '2026-10-20');
    assert.equal(to.getAttribute('min'), null);

    from.setValue('2026-10-02');
    assert.equal(to.getAttribute('min'), '2026-10-02');
    assert.equal(document.getElementById('_sale_price_dates_from').value, '2026-10-02');

    to.clear();
    assert.equal(from.getAttribute('max'), null);
});

test('cancelling a sale schedule clears both pickers', async () => {
    const window = await page(`<div id="woocommerce-product-data">${saleDates({ from: '2026-10-02', to: '2026-10-10' })}</div>`);
    const { document, jQuery: $ } = window;

    // WooCommerce's handler (meta-boxes-product.js).
    $('#woocommerce-product-data').on('click', '.cancel_sale_schedule', function () {
        const $wrap = $(this).closest('div, table');
        $wrap.find('.sale_price_dates_fields').hide();
        $wrap.find('.sale_price_dates_fields').find('input').val('');
        return false;
    });

    document.querySelector('.cancel_sale_schedule').click();
    await tick(window);

    const from = document.getElementById('_sale_price_dates_from');
    const to = document.getElementById('_sale_price_dates_to');
    assert.equal(from.value, '');
    assert.equal(to.value, '');
    assert.equal(pickerOf(window, from).value, '');
    assert.equal(pickerOf(window, to).value, '');
    assert.equal(pickerOf(window, to).getAttribute('min'), null);
    assert.equal(pickerOf(window, from).getAttribute('max'), null);
});

test('variations loaded later get pickers, and a picked date marks the variation changed', async () => {
    const window = await page('<div id="woocommerce-product-data"><div id="variable_product_options"><div class="woocommerce_variations"></div></div></div>');
    const { document, jQuery: $ } = window;
    const changed = [];

    // WooCommerce's handler (meta-boxes-product-variation.js).
    $(document.body).on('change input', '#variable_product_options .woocommerce_variations :input', function () {
        $(this).closest('.woocommerce_variation').addClass('variation-needs-update');
        changed.push(this.name);
    });

    // As load_variations() does.
    $('.woocommerce_variations').empty().append(variation(0) + variation(1, { from: '2026-10-02', to: '2026-10-10' }));
    $('#woocommerce-product-data').trigger('woocommerce_variations_loaded');

    const from0 = document.querySelector('input[name="variable_sale_price_dates_from[0]"]');
    const to1 = document.querySelector('input[name="variable_sale_price_dates_to[1]"]');
    assert.equal(from0.type, 'hidden');
    assert.equal(pickerOf(window, to1).value, '2026-10-10');
    assert.equal(pickerOf(window, to1).getAttribute('min'), '2026-10-02');
    assert.deepEqual(changed, []);

    pickerOf(window, from0).setValue('2026-10-05');
    assert.equal(from0.value, '2026-10-05');
    assert.deepEqual(changed, ['variable_sale_price_dates_from[0]', 'variable_sale_price_dates_from[0]']);
    assert.ok(from0.closest('.woocommerce_variation').classList.contains('variation-needs-update'));
    assert.ok(!to1.closest('.woocommerce_variation').classList.contains('variation-needs-update'));
    assert.equal(pickerOf(window, document.querySelector('input[name="variable_sale_price_dates_to[0]"]')).getAttribute('min'), '2026-10-05');

    // Download permissions are appended without an event of their own.
    const permissions = document.createElement('div');
    permissions.innerHTML = downloadPermission(3);
    document.body.appendChild(permissions);
    await tick(window);
    assert.equal(document.querySelector('input[name="access_expires[3]"]').type, 'hidden');
});

test('Jalali values written by date_i18n() are saved as Gregorian', async () => {
    const window = await page(`
        <div id="woocommerce-product-data">${saleDates({ from: '1405-07-10', to: '۱۴۰۵-۰۷-۲۰' })}</div>
        ${orderDate({ date: '1405-07-10' })}`);
    const { document } = window;

    assert.equal(document.getElementById('_sale_price_dates_from').value, '2026-10-02');
    assert.equal(document.getElementById('_sale_price_dates_to').value, '2026-10-12');
    assert.equal(document.querySelector('input[name="order_date"]').value, '2026-10-02');
    assert.equal(pickerOf(window, document.getElementById('_sale_price_dates_to')).getAttribute('min'), '2026-10-02');
});

test('Persian digits in the order hour and minute become English', async () => {
    const window = await page(orderDate({ hour: '۰۹' }));
    const { document } = window;
    const hour = document.querySelector('input[name="order_date_hour"]');
    const minute = document.querySelector('input[name="order_date_minute"]');

    // A number input drops a value it cannot read; the attribute keeps it.
    assert.equal(hour.value, '09');

    // Number inputs refuse Persian digits as they are typed.
    assert.equal(hour.type, 'text');
    assert.equal(hour.getAttribute('inputmode'), 'numeric');
    assert.equal(minute.type, 'text');
    assert.equal(minute.value, '05');

    minute.value = '۴۵';
    minute.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.equal(minute.value, '45');

    hour.value = '';
    window.jQuery(document.body).trigger('wc-init-datepickers');
    assert.equal(hour.value, '');
});
