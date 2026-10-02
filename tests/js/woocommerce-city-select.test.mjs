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
    ABZ: ['کرج', 'آسارا'],
    THR: ['تهران', 'اسلامشهر'],
};

/**
 * The classic checkout's address fields, as WooCommerce renders them.
 *
 * @param {object} options
 * @param {string} options.country
 * @param {string} options.state
 * @param {string} options.city  The city field's markup; a text input by default.
 */
function fieldsHtml(group, { country = 'IR', state = 'QHM', city = '' } = {}) {
    const option = (value) => `<option value="${value}"${value === country || value === state ? ' selected' : ''}>${value}</option>`;

    return `
        <div class="woocommerce-${group}-fields">
            <select id="${group}_country" name="${group}_country">${['DE', 'IR'].map(option).join('')}</select>
            <select id="${group}_state" name="${group}_state"><option value=""></option>${Object.keys(CITIES).map(option).join('')}</select>
            ${city || `<input type="text" class="input-text" name="${group}_city" id="${group}_city" autocomplete="address-level2" value="">`}
        </div>`;
}

/** A page with jQuery and the script loaded, after its first run. */
async function page(body, { cities = CITIES, selectWoo = false } = {}) {
    const dom = new JSDOM(`<!doctype html><html><body>${body}</body></html>`, { runScripts: 'outside-only' });
    const { window } = dom;
    window.eval(jquerySource);

    if (cities) {
        window.persianKitCities = { cities, placeholder: 'Select a city…' };
    }

    const selectWooCalls = [];
    if (selectWoo) {
        window.jQuery.fn.selectWoo = function (options) {
            selectWooCalls.push(options);
            this.data('select2', options !== 'destroy' || undefined);
            if (options === 'destroy') {
                this.removeData('select2');
            }
            return this;
        };
    }

    window.eval(source);
    await loaded(window);

    return { window, $: window.jQuery, selectWooCalls };
}

/** jsdom finishes loading asynchronously; jQuery's ready handlers run after that. */
async function loaded(window) {
    if (window.document.readyState !== 'complete') {
        await new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));
    }
    await new Promise((resolve) => window.setTimeout(resolve, 0));
}

/** The city options' values, as an array of this realm (jsdom's arrays fail deepStrictEqual). */
function cityOptions($, group = 'billing') {
    return Array.from($(`#${group}_city option`), (option) => option.value);
}

test('an Iranian address gets a dropdown of the province cities', async () => {
    const { $ } = await page(fieldsHtml('billing'));
    const $city = $('#billing_city');

    assert.ok($city.is('select'));
    assert.deepEqual(cityOptions($), ['', 'قم', 'جعفریه', 'کهک']);
    assert.equal($city.find('option').first().text(), 'Select a city…');
    // WooCommerce reads the field by its name; autocomplete stays for the browser.
    assert.equal($city.attr('name'), 'billing_city');
    assert.equal($city.attr('autocomplete'), 'address-level2');
    assert.equal($('[name="billing_city"]').length, 1);
});

test('a city typed before stays selected when the province has it', async () => {
    const { $ } = await page(fieldsHtml('billing', {
        state: 'ABZ',
        city: '<input type="text" class="input-text" name="billing_city" id="billing_city" value="کرج">',
    }));

    assert.equal($('#billing_city').val(), 'کرج');
});

test('changing the province refills the list and drops a city it lacks', async () => {
    const { $ } = await page(fieldsHtml('billing'));
    $('#billing_city').val('جعفریه');

    $('#billing_state').val('ABZ').trigger('change');

    assert.deepEqual(cityOptions($), ['', 'کرج', 'آسارا']);
    assert.equal($('#billing_city').val(), '');
});

test('a province with no list gives only the placeholder', async () => {
    const { $ } = await page(fieldsHtml('billing', { state: '' }));

    assert.deepEqual(cityOptions($), ['']);
});

test('another country turns the dropdown back into a text field, keeping the city', async () => {
    const { $, window } = await page(fieldsHtml('billing'));
    $('#billing_city').val('قم');

    $('#billing_country').val('DE');
    $(window.document.body).trigger('country_to_state_changed', ['DE', $('.woocommerce-billing-fields')]);

    const $city = $('#billing_city');
    assert.ok($city.is('input[type="text"]'));
    assert.equal($city.attr('name'), 'billing_city');
    assert.equal($city.val(), 'قم');
    assert.equal($('[name="billing_city"]').length, 1);
});

test('choosing Iran again brings the dropdown back', async () => {
    const { $, window } = await page(fieldsHtml('billing', { country: 'DE' }));
    assert.ok($('#billing_city').is('input'), 'starts as text for Germany');

    $('#billing_country').val('IR');
    $('#billing_state').val('THR');
    $(window.document.body).trigger('country_to_state_changed', ['IR', $('.woocommerce-billing-fields')]);

    assert.ok($('#billing_city').is('select'));
    assert.deepEqual(cityOptions($), ['', 'تهران', 'اسلامشهر']);
});

test('a saved city the list lacks stays selected until the province changes', async () => {
    // The server adds the saved city to the options it renders.
    const { $ } = await page(fieldsHtml('billing', {
        city: `<select name="billing_city" id="billing_city">
            <option value="">Select a city…</option><option value="قم">قم</option>
            <option value="روستای من" selected>روستای من</option></select>`,
    }));

    assert.equal($('#billing_city').val(), 'روستای من');
    assert.deepEqual(cityOptions($), ['', 'قم', 'جعفریه', 'کهک', 'روستای من']);

    $('#billing_state').val('ABZ').trigger('change');

    assert.equal($('#billing_city').val(), '');
    assert.deepEqual(cityOptions($), ['', 'کرج', 'آسارا']);
});

test('billing and shipping are filled separately', async () => {
    const { $ } = await page(fieldsHtml('billing') + fieldsHtml('shipping', { state: 'THR' }));

    $('#shipping_state').val('ABZ').trigger('change');

    assert.deepEqual(cityOptions($, 'billing'), ['', 'قم', 'جعفریه', 'کهک']);
    assert.deepEqual(cityOptions($, 'shipping'), ['', 'کرج', 'آسارا']);
});

test('selectWoo is used when WooCommerce loaded it, and removed for a text field', async () => {
    const { $, window, selectWooCalls } = await page(fieldsHtml('billing'), { selectWoo: true });

    assert.equal(selectWooCalls.length, 1);
    assert.equal(selectWooCalls[0].width, '100%');

    $('#billing_country').val('DE');
    $(window.document.body).trigger('country_to_state_changed', ['DE', $('.woocommerce-billing-fields')]);

    assert.equal(selectWooCalls.at(-1), 'destroy');
    assert.ok($('#billing_city').is('input'));
});

test('without the city data the script leaves the page alone', async () => {
    const { $ } = await page(fieldsHtml('billing'), { cities: null });

    assert.ok($('#billing_city').is('input'));
});

test('the block checkout, which has no classic city field, is left alone', async () => {
    const { $ } = await page('<input id="billing-city" name="billing-city" value="">');

    assert.ok($('#billing-city').is('input'));
});
