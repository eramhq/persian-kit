import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const jquerySource = readFileSync(require.resolve('jquery/dist/jquery.js'), 'utf8');
const source = readFileSync(new URL('../../resources/js/woocommerce-city-select.js', import.meta.url), 'utf8');

const CITIES = {
    QHM: ['قم', 'جعفریه', 'کهک'],
    ABZ: ['کرج', 'آسارا', 'نظرآباد'],
    THR: ['تهران', 'اسلامشهر', 'نظرآباد'],
};

function selectHtml(attributes, values, selected) {
    const options = values.map((value) => `<option value="${value}"${value === selected ? ' selected' : ''}>${value}</option>`);

    return `<select ${attributes}>${options.join('')}</select>`;
}

/** The classic checkout's address fields, as WooCommerce renders them. */
function classicHtml(group, { country = 'IR', state = 'QHM', city = '' } = {}) {
    return `
        <div class="woocommerce-${group}-fields">
            ${selectHtml(`id="${group}_country" name="${group}_country" autocomplete="country"`, ['DE', 'IR'], country)}
            ${selectHtml(`id="${group}_state" name="${group}_state" autocomplete="address-level1"`, ['', ...Object.keys(CITIES)], state)}
            <input type="text" class="input-text" name="${group}_city" id="${group}_city" autocomplete="address-level2" value="${city}">
        </div>`;
}

/** The block checkout's address form: ids with dashes, sectioned autocomplete tokens. */
function blockHtml(group, { country = 'IR', state = 'QHM', city = '' } = {}) {
    const ac = (token) => `autocomplete="section-${group} ${group} ${token}"`;

    return `
        <div class="wc-block-components-address-form" id="${group}">
            ${selectHtml(`id="${group}-country" ${ac('country')}`, ['DE', 'IR'], country)}
            ${selectHtml(`id="${group}-state" ${ac('address-level1')}`, ['', ...Object.keys(CITIES)], state)}
            <input type="text" id="${group}-city" ${ac('address-level2')} value="${city}">
        </div>`;
}

/** A page with the script loaded, after its first run. */
async function page(body, { cities = CITIES, jquery = false } = {}) {
    const dom = new JSDOM(`<!doctype html><html><body>${body}</body></html>`, { runScripts: 'outside-only' });
    const { window } = dom;
    if (jquery) {
        window.eval(jquerySource);
    }
    if (cities) {
        window.persianKitCities = { cities };
    }

    window.eval(source);
    await tick(window);

    return { window, document: window.document, $: window.jQuery };
}

/** Lets the mutation observer and its batched scan run. */
async function tick(window) {
    await new Promise((resolve) => window.setTimeout(resolve, 30));
}

/** The values the field's datalist suggests, as an array of this realm. */
function suggestions(document, city) {
    const id = city.getAttribute('list');
    if (!id) {
        return null;
    }

    return Array.from(document.getElementById(id).options, (option) => option.value);
}

/** A customer picking a value: the browser sets it and fires change. */
function choose(window, field, value) {
    field.value = value;
    field.dispatchEvent(new window.Event('change', { bubbles: true }));
}

function focus(window, field) {
    field.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));
}

test('an Iranian address with a province gets that province as suggestions', async () => {
    const { document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    assert.deepEqual(suggestions(document, city), ['قم', 'جعفریه', 'کهک']);
    assert.equal(document.querySelector(`#${city.getAttribute('list')}`).tagName, 'DATALIST');
    assert.ok(!document.getElementById('billing').contains(document.getElementById(city.getAttribute('list'))), 'the datalist stays outside the form');
});

test('a province with no list, or none chosen yet, gives no suggestions', async () => {
    const { document } = await page(blockHtml('billing', { state: '' }));

    assert.equal(document.getElementById('billing-city').hasAttribute('list'), false);
});

test('the list follows a province changed without an event once the city is focused', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }));
    const city = document.getElementById('billing-city');

    // React sets the value on a saved-address load, firing nothing.
    document.getElementById('billing-state').value = 'THR';
    focus(window, city);

    assert.deepEqual(suggestions(document, city), ['تهران', 'اسلامشهر', 'نظرآباد']);
    assert.equal(city.value, 'قم', 'focusing never clears the city');
});

test('picking another province clears a city of the old one, firing input and change', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'جعفریه' }));
    const city = document.getElementById('billing-city');
    const events = [];
    city.addEventListener('input', () => events.push('input'));
    city.addEventListener('change', () => events.push('change'));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(city.value, '');
    assert.deepEqual(events, ['input', 'change']);
    assert.deepEqual(suggestions(document, city), ['کرج', 'آسارا', 'نظرآباد']);
});

test('a typed name on no list is kept when the province changes', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'روستای من' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, 'روستای من');
});

test('a name both provinces have is kept', async () => {
    const { window, document } = await page(blockHtml('billing', { state: 'THR', city: 'نظرآباد' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, 'نظرآباد');
});

test('a saved city is not cleared on load, even when it is not the province\'s', async () => {
    const { window, document } = await page(classicHtml('billing', { city: 'تهران' }), { jquery: true });
    const city = document.getElementById('billing_city');

    // WooCommerce's classic scripts re-fire change on load with the same province.
    window.jQuery('#billing_state').trigger('change');

    assert.equal(city.value, 'تهران');
    assert.deepEqual(suggestions(document, city), ['قم', 'جعفریه', 'کهک']);
});

test('another country removes the list and Iran brings it back', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }));
    const city = document.getElementById('billing-city');

    choose(window, document.getElementById('billing-country'), 'DE');
    assert.equal(city.hasAttribute('list'), false);
    assert.equal(city.value, 'قم');

    choose(window, document.getElementById('billing-country'), 'IR');
    assert.deepEqual(suggestions(document, city), ['قم', 'جعفریه', 'کهک']);
});

test('a province change outside Iran never clears the city', async () => {
    const { window, document } = await page(blockHtml('billing', { country: 'DE', city: 'قم' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, 'قم');
});

test('a form that appears later is picked up', async () => {
    const { window, document } = await page(blockHtml('billing'));

    const wrapper = document.createElement('div');
    wrapper.innerHTML = blockHtml('shipping', { state: 'THR' });
    document.body.appendChild(wrapper);
    await tick(window);

    assert.deepEqual(suggestions(document, document.getElementById('shipping-city')), ['تهران', 'اسلامشهر', 'نظرآباد']);
});

test('billing and shipping are separate', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }) + blockHtml('shipping', { state: 'THR', city: 'تهران' }));
    const billing = document.getElementById('billing-city');
    const shipping = document.getElementById('shipping-city');

    choose(window, document.getElementById('shipping-state'), 'ABZ');

    assert.equal(shipping.value, '');
    assert.equal(billing.value, 'قم');
    assert.deepEqual(suggestions(document, billing), ['قم', 'جعفریه', 'کهک']);
    assert.deepEqual(suggestions(document, shipping), ['کرج', 'آسارا', 'نظرآباد']);
    assert.notEqual(billing.getAttribute('list'), shipping.getAttribute('list'));
});

test('fields are found by autocomplete token when their ids say nothing', async () => {
    const { window, document } = await page(`
        <form class="woocommerce-address-fields">
            ${selectHtml('id="a" autocomplete="country"', ['DE', 'IR'], 'IR')}
            ${selectHtml('id="b" autocomplete="address-level1"', ['', 'QHM', 'ABZ'], 'ABZ')}
            <input type="text" id="c" autocomplete="address-level2" value="">
        </form>`);

    assert.deepEqual(suggestions(document, document.getElementById('c')), ['کرج', 'آسارا', 'نظرآباد']);

    choose(window, document.getElementById('b'), 'QHM');
    assert.deepEqual(suggestions(document, document.getElementById('c')), ['قم', 'جعفریه', 'کهک']);
});

test('the classic cart calculator is found by id and its jQuery-only events are heard', async () => {
    const { $, document } = await page(`
        <form class="woocommerce-shipping-calculator">
            ${selectHtml('id="calc_shipping_country" name="calc_shipping_country"', ['DE', 'IR'], 'IR')}
            ${selectHtml('id="calc_shipping_state" name="calc_shipping_state"', ['', 'QHM', 'ABZ'], 'QHM')}
            <input type="text" class="input-text" id="calc_shipping_city" name="calc_shipping_city" value="کهک">
        </form>`, { jquery: true });
    const city = document.getElementById('calc_shipping_city');

    assert.deepEqual(suggestions(document, city), ['قم', 'جعفریه', 'کهک']);

    // selectWoo triggers change through jQuery only.
    $('#calc_shipping_state').val('ABZ').trigger('change');

    assert.equal(city.value, '');
    assert.deepEqual(suggestions(document, city), ['کرج', 'آسارا', 'نظرآباد']);
});

test('the classic form follows WooCommerce\'s redrawn state field', async () => {
    const { $, window, document } = await page(classicHtml('billing', { country: 'DE', state: '' }), { jquery: true });
    const city = document.getElementById('billing_city');
    assert.equal(city.hasAttribute('list'), false);

    // WooCommerce swaps in a new state select, then fires its event.
    $('#billing_country').val('IR');
    $('#billing_state').replaceWith(selectHtml('id="billing_state" name="billing_state"', ['', 'THR'], 'THR'));
    $(window.document.body).trigger('country_to_state_changed', ['IR', $('.woocommerce-billing-fields')]);

    assert.deepEqual(suggestions(document, city), ['تهران', 'اسلامشهر', 'نظرآباد']);
});

test('a city that another plugin made a select is left alone', async () => {
    const { document } = await page(`
        <div class="woocommerce-billing-fields">
            ${selectHtml('id="billing_country" autocomplete="country"', ['IR'], 'IR')}
            ${selectHtml('id="billing_state" autocomplete="address-level1"', ['QHM'], 'QHM')}
            ${selectHtml('id="billing_city" autocomplete="address-level2"', ['قم'], 'قم')}
        </div>`);

    const city = document.getElementById('billing_city');
    assert.equal(city.hasAttribute('list'), false);
    assert.equal(city.hasAttribute('data-persian-kit-city'), false);
    assert.equal(document.querySelectorAll('datalist').length, 0);
});

test('without the city data the script does nothing', async () => {
    const { document } = await page(blockHtml('billing'), { cities: null });

    assert.equal(document.getElementById('billing-city').hasAttribute('list'), false);
    assert.equal(document.querySelectorAll('datalist').length, 0);
});
