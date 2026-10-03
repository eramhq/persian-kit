import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { JSDOM } from 'jsdom';

const require = createRequire(import.meta.url);
const jquerySource = readFileSync(require.resolve('jquery/dist/jquery.js'), 'utf8');
const source = readFileSync(new URL('../../resources/js/woocommerce-city-select.js', import.meta.url), 'utf8');
// As CityField passes them: without the keys starting with "_", which hold the source.
const realCities = Object.fromEntries(Object.entries(JSON.parse(readFileSync(new URL('../../resources/data/ir-cities.json', import.meta.url), 'utf8')))
    .filter(([state]) => !state.startsWith('_')));
const cityMatches = JSON.parse(readFileSync(new URL('../fixtures/city-matches.json', import.meta.url), 'utf8')).cases;
const I18N = { label: 'City suggestions', oneResult: '1 city suggested.', results: '%d cities suggested.', noResults: 'No matching city' };

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
function blockHtml(group, { country = 'IR', state = 'QHM', city = '', states = Object.keys(CITIES) } = {}) {
    const ac = (token) => `autocomplete="section-${group} ${group} ${token}"`;

    return `
        <div class="wc-block-components-address-form" id="${group}">
            ${selectHtml(`id="${group}-country" ${ac('country')}`, ['DE', 'IR'], country)}
            ${selectHtml(`id="${group}-state" ${ac('address-level1')}`, ['', ...states], state)}
            <input type="text" id="${group}-city" ${ac('address-level2')} value="${city}">
        </div>`;
}

/** A page with the script loaded, after its first run. */
async function page(body, { cities = CITIES, jquery = false } = {}) {
    const dom = new JSDOM(`<!doctype html><html dir="rtl"><body>${body}</body></html>`, { runScripts: 'outside-only' });
    const { window } = dom;
    // jsdom has no layout.
    window.HTMLElement.prototype.scrollIntoView = function () {};
    if (jquery) {
        window.eval(jquerySource);
    }
    if (cities) {
        window.persianKitCities = { cities, i18n: I18N };
    }

    window.eval(source);
    await tick(window);

    return { window, document: window.document, $: window.jQuery };
}

/** Lets the mutation observer and its batched scan run. */
async function tick(window) {
    await new Promise((resolve) => window.setTimeout(resolve, 30));
}

function listbox(document, city) {
    const id = city.getAttribute('aria-controls');

    return id ? document.getElementById(id) : null;
}

/** The names the open list shows, as an array of this realm; [] when closed. */
function shown(document, city) {
    const list = listbox(document, city);
    if (!list || list.hidden) {
        return [];
    }

    return Array.from(list.querySelectorAll('[role="option"]'), (option) => option.textContent);
}

/** A customer typing: the browser sets the value and fires an InputEvent. */
function type(window, city, text) {
    city.focus();
    city.value = text;
    city.dispatchEvent(new window.InputEvent('input', { bubbles: true, inputType: 'insertText', data: text }));
}

function press(window, city, key) {
    const event = new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
    city.dispatchEvent(event);

    return event;
}

/** A customer picking a value in a select: the browser sets it and fires change. */
function choose(window, field, value) {
    field.value = value;
    field.dispatchEvent(new window.Event('change', { bubbles: true }));
}

function focus(window, field) {
    field.dispatchEvent(new window.FocusEvent('focusin', { bubbles: true }));
}

/** Records the input and change events the field fires. */
function record(city) {
    const events = [];
    city.addEventListener('input', () => events.push('input'));
    city.addEventListener('change', () => events.push('change'));

    return events;
}

test('the city field becomes a combobox for its province\'s list', async () => {
    const { document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');
    const list = listbox(document, city);

    assert.equal(city.getAttribute('role'), 'combobox');
    assert.equal(city.getAttribute('aria-autocomplete'), 'list');
    assert.equal(city.getAttribute('aria-expanded'), 'false');
    assert.equal(list.getAttribute('role'), 'listbox');
    assert.equal(list.hidden, true);
    assert.equal(city.type, 'text', 'still a text field');
    assert.ok(!document.getElementById('billing').contains(list), 'the list stays outside the form');
    assert.equal(document.querySelectorAll('datalist').length, 0);
    assert.equal(city.hasAttribute('list'), false);
});

test('typing opens the list with the matching cities', async () => {
    const { window, document } = await page(blockHtml('billing', { state: 'THR' }));
    const city = document.getElementById('billing-city');

    type(window, city, 'اسلام');

    assert.deepEqual(shown(document, city), ['اسلامشهر']);
    assert.equal(city.getAttribute('aria-expanded'), 'true');
    assert.equal(city.hasAttribute('aria-activedescendant'), false, 'nothing is chosen until the arrows are used');
    assert.equal(document.querySelector('[role="status"]').textContent, '1 city suggested.');
});

test('matching ignores spaces, half-spaces and other spellings', async () => {
    const cities = { MZN: ['ساری', 'آمل', 'قایم شهر'], ADL: ['اردبیل', 'مشگین شهر'], THR: ['تهران', 'شهریار'] };
    const { window, document } = await page(blockHtml('billing', { state: 'MZN', states: Object.keys(cities) }), { cities });
    const city = document.getElementById('billing-city');
    const state = document.getElementById('billing-state');

    type(window, city, 'قائمشهر');
    assert.deepEqual(shown(document, city), ['قایم شهر']);

    type(window, city, 'امل');
    assert.deepEqual(shown(document, city), ['آمل']);

    choose(window, state, 'ADL');
    type(window, city, 'مشکین');
    assert.deepEqual(shown(document, city), ['مشگین شهر']);

    choose(window, state, 'THR');
    type(window, city, 'شهريار');
    assert.deepEqual(shown(document, city), ['شهریار'], 'Arabic yeh');
});

test('the same name comes first, then names that start with it, then names that contain it', async () => {
    const cities = { FRS: ['شیراز', 'شهر صدرا', 'صدرا آباد', 'باصدرا', 'صدرا'] };
    const { window, document } = await page(blockHtml('billing', { state: 'FRS', states: ['FRS'] }), { cities });
    const city = document.getElementById('billing-city');

    type(window, city, 'صدرا');

    assert.deepEqual(shown(document, city), ['صدرا', 'شهر صدرا', 'صدرا آباد', 'باصدرا']);
});

test('one letter finds names starting with it, not names containing it', async () => {
    const { window, document } = await page(blockHtml('billing', { state: 'THR' }));
    const city = document.getElementById('billing-city');

    type(window, city, 'ن');

    assert.deepEqual(shown(document, city), ['نظرآباد']);
});

test('at most eight cities are shown', async () => {
    const cities = { THR: Array.from({ length: 12 }, (_, index) => `شهر ${index + 1}`) };
    const { window, document } = await page(blockHtml('billing', { state: 'THR', states: ['THR'] }), { cities });
    const city = document.getElementById('billing-city');

    type(window, city, 'شهر');

    assert.equal(shown(document, city).length, 8);
    assert.deepEqual(shown(document, city).slice(0, 2), ['شهر 1', 'شهر 2'], 'list order kept');
});

test('nothing matching closes the list and says so', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    type(window, city, 'روستای من');

    assert.deepEqual(shown(document, city), []);
    assert.equal(city.getAttribute('aria-expanded'), 'false');
    assert.equal(document.querySelector('[role="status"]').textContent, 'No matching city');
});

test('arrows move the active option and Enter picks it', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');
    const list = listbox(document, city);

    // ArrowDown on an empty field shows the whole list.
    const down = press(window, city, 'ArrowDown');
    assert.equal(down.defaultPrevented, true);
    assert.deepEqual(shown(document, city), ['قم', 'جعفریه', 'کهک']);
    assert.equal(document.querySelector('[role="status"]').textContent, '3 cities suggested.');

    const active = () => document.getElementById(city.getAttribute('aria-activedescendant'));
    assert.equal(active().textContent, 'قم');
    assert.equal(active().getAttribute('aria-selected'), 'true');

    press(window, city, 'ArrowDown');
    assert.equal(active().textContent, 'جعفریه');
    assert.equal(list.querySelectorAll('[aria-selected="true"]').length, 1);

    press(window, city, 'ArrowUp');
    press(window, city, 'ArrowUp');
    assert.equal(active().textContent, 'کهک', 'wraps around');

    const events = record(city);
    const enter = press(window, city, 'Enter');

    assert.equal(enter.defaultPrevented, true, 'the form is not submitted');
    assert.equal(city.value, 'کهک');
    assert.deepEqual(events, ['input', 'change']);
    assert.equal(list.hidden, true, 'a pick does not reopen the list');
    assert.equal(city.getAttribute('aria-expanded'), 'false');
    assert.equal(city.hasAttribute('aria-activedescendant'), false);
});

test('arrow keys do not reach the form\'s own handlers', async () => {
    const { window, document } = await page(classicHtml('billing'), { jquery: true });
    const city = document.getElementById('billing_city');
    const reached = [];
    window.jQuery(document.querySelector('.woocommerce-billing-fields')).on('keydown', 'input', (event) => reached.push(event.key));

    press(window, city, 'ArrowDown');
    press(window, city, 'Escape');
    press(window, city, 'a');

    assert.deepEqual(reached, ['a']);
});

test('Enter with no active option, or with the list closed, is left to the form', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    assert.equal(press(window, city, 'Enter').defaultPrevented, false);

    type(window, city, 'ق');
    assert.equal(press(window, city, 'Enter').defaultPrevented, false);
    assert.equal(city.value, 'ق');
});

test('a click picks an option, keeping the focus in the field', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    type(window, city, 'ک');
    const option = listbox(document, city).querySelector('[role="option"]');
    const events = record(city);

    const down = new window.MouseEvent('mousedown', { bubbles: true, cancelable: true });
    option.dispatchEvent(down);
    assert.equal(down.defaultPrevented, true, 'the field keeps the focus');
    assert.equal(listbox(document, city).hidden, false);

    option.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));

    assert.equal(city.value, 'کهک');
    assert.deepEqual(events, ['input', 'change']);
    assert.deepEqual(shown(document, city), []);
});

test('Escape, Tab and a click outside close the list', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    type(window, city, 'ق');
    const escape = press(window, city, 'Escape');
    assert.deepEqual(shown(document, city), []);
    assert.equal(escape.defaultPrevented, true);
    assert.equal(city.value, 'ق', 'nothing picked');

    type(window, city, 'ق');
    const tab = press(window, city, 'Tab');
    assert.deepEqual(shown(document, city), []);
    assert.equal(tab.defaultPrevented, false, 'focus still moves on');
    assert.equal(city.value, 'ق');

    type(window, city, 'ق');
    document.body.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true }));
    assert.deepEqual(shown(document, city), []);
});

test('Escape with the list closed is left alone', async () => {
    const { window, document } = await page(blockHtml('billing'));

    assert.equal(press(window, document.getElementById('billing-city'), 'Escape').defaultPrevented, false);
});

test('autofill and focus do not open the list', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    city.focus();
    focus(window, city);
    assert.deepEqual(shown(document, city), []);

    // Browser autofill fires a plain Event.
    city.value = 'قم';
    city.dispatchEvent(new window.Event('input', { bubbles: true }));
    assert.deepEqual(shown(document, city), []);

    city.dispatchEvent(new window.InputEvent('input', { bubbles: true, inputType: 'insertReplacementText' }));
    assert.deepEqual(shown(document, city), []);
});

test('the options are text, never markup', async () => {
    const cities = { THR: ['<img src=x onerror=alert(1)>تهران'] };
    const { window, document } = await page(blockHtml('billing', { state: 'THR', states: ['THR'] }), { cities });
    const city = document.getElementById('billing-city');

    type(window, city, 'تهران');

    assert.deepEqual(shown(document, city), ['<img src=x onerror=alert(1)>تهران']);
    assert.equal(document.querySelectorAll('img').length, 0);
});

test('the shared cases find the listed name first', async () => {
    const states = Object.keys(realCities);

    for (const { state, typed, official } of cityMatches) {
        const { window, document } = await page(blockHtml('billing', { state, states }), { cities: realCities });
        const city = document.getElementById('billing-city');

        type(window, city, typed);
        const names = shown(document, city);

        if (official === null) {
            assert.ok(!names.includes(typed), `${state} ${typed}: ${names.join(', ')}`);
        } else {
            assert.equal(names[0], official, `${state} ${typed}`);
        }
    }
});

test('a province with no list, or none chosen yet, gives no list', async () => {
    const { window, document } = await page(blockHtml('billing', { state: '' }));
    const city = document.getElementById('billing-city');

    assert.equal(city.hasAttribute('role'), false);
    type(window, city, 'ق');
    assert.equal(press(window, city, 'ArrowDown').defaultPrevented, false);
    assert.deepEqual(shown(document, city), []);
});

test('the list follows a province changed without an event once the city is focused', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }));
    const city = document.getElementById('billing-city');

    // React sets the value on a saved-address load, firing nothing.
    document.getElementById('billing-state').value = 'THR';
    focus(window, city);
    city.value = '';
    press(window, city, 'ArrowDown');

    assert.deepEqual(shown(document, city), ['تهران', 'اسلامشهر', 'نظرآباد']);
});

test('focusing never clears the city', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }));
    const city = document.getElementById('billing-city');

    document.getElementById('billing-state').value = 'THR';
    focus(window, city);

    assert.equal(city.value, 'قم');
});

test('picking another province clears a city of the old one, firing input and change', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'جعفریه' }));
    const city = document.getElementById('billing-city');
    const events = record(city);

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(city.value, '');
    assert.deepEqual(events, ['input', 'change']);
    assert.deepEqual(shown(document, city), [], 'clearing does not open the list');
    press(window, city, 'ArrowDown');
    assert.deepEqual(shown(document, city), ['کرج', 'آسارا', 'نظرآباد']);
});

test('a city of the old province typed another way is cleared too', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'جعفريه' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, '');
});

test('a province change closes an open list', async () => {
    const { window, document } = await page(blockHtml('billing'));
    const city = document.getElementById('billing-city');

    type(window, city, 'ق');
    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.deepEqual(shown(document, city), []);
});

test('a typed name on no list is kept when the province changes', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'روستای من' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, 'روستای من');
});

test('a name both provinces have is kept', async () => {
    const { window, document } = await page(blockHtml('billing', { state: 'THR', city: 'نظر آباد' }));

    choose(window, document.getElementById('billing-state'), 'ABZ');

    assert.equal(document.getElementById('billing-city').value, 'نظر آباد');
});

test('a saved city is not cleared on load, even when it is not the province\'s', async () => {
    const { window, document } = await page(classicHtml('billing', { city: 'تهران' }), { jquery: true });
    const city = document.getElementById('billing_city');

    // WooCommerce's classic scripts re-fire change on load with the same province.
    window.jQuery('#billing_state').trigger('change');

    assert.equal(city.value, 'تهران');
    assert.equal(city.getAttribute('role'), 'combobox');
});

test('another country removes the combobox and Iran brings it back', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }));
    const city = document.getElementById('billing-city');

    choose(window, document.getElementById('billing-country'), 'DE');
    assert.equal(city.hasAttribute('role'), false);
    assert.equal(city.hasAttribute('aria-expanded'), false);
    assert.equal(city.value, 'قم');

    choose(window, document.getElementById('billing-country'), 'IR');
    assert.equal(city.getAttribute('role'), 'combobox');
    type(window, city, 'ک');
    assert.deepEqual(shown(document, city), ['کهک']);
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

    const city = document.getElementById('shipping-city');
    type(window, city, 'تهران');
    assert.deepEqual(shown(document, city), ['تهران']);
});

test('a field taken out of the page takes its list with it', async () => {
    const { window, document } = await page(blockHtml('billing') + blockHtml('shipping'));
    const list = listbox(document, document.getElementById('shipping-city'));

    document.getElementById('shipping').remove();
    document.body.appendChild(document.createElement('p'));
    await tick(window);

    assert.equal(list.isConnected, false);
    assert.equal(document.querySelectorAll('[role="listbox"]').length, 1);
});

test('billing and shipping are separate', async () => {
    const { window, document } = await page(blockHtml('billing', { city: 'قم' }) + blockHtml('shipping', { state: 'THR', city: 'تهران' }));
    const billing = document.getElementById('billing-city');
    const shipping = document.getElementById('shipping-city');

    choose(window, document.getElementById('shipping-state'), 'ABZ');

    assert.equal(shipping.value, '');
    assert.equal(billing.value, 'قم');
    assert.notEqual(billing.getAttribute('aria-controls'), shipping.getAttribute('aria-controls'));

    type(window, billing, 'ک');
    assert.deepEqual(shown(document, billing), ['کهک']);
    type(window, shipping, 'ک');
    assert.deepEqual(shown(document, shipping), ['کرج']);
    assert.deepEqual(shown(document, billing), [], 'one list is open at a time');
});

test('fields are found by autocomplete token when their ids say nothing', async () => {
    const { window, document } = await page(`
        <form class="woocommerce-address-fields">
            ${selectHtml('id="a" autocomplete="country"', ['DE', 'IR'], 'IR')}
            ${selectHtml('id="b" autocomplete="address-level1"', ['', 'QHM', 'ABZ'], 'ABZ')}
            <input type="text" id="c" autocomplete="address-level2" value="">
        </form>`);
    const city = document.getElementById('c');

    type(window, city, 'ک');
    assert.deepEqual(shown(document, city), ['کرج']);

    choose(window, document.getElementById('b'), 'QHM');
    type(window, city, 'ک');
    assert.deepEqual(shown(document, city), ['کهک']);
});

test('the classic cart calculator is found by id and its jQuery-only events are heard', async () => {
    const { $, window, document } = await page(`
        <form class="woocommerce-shipping-calculator">
            ${selectHtml('id="calc_shipping_country" name="calc_shipping_country"', ['DE', 'IR'], 'IR')}
            ${selectHtml('id="calc_shipping_state" name="calc_shipping_state"', ['', 'QHM', 'ABZ'], 'QHM')}
            <input type="text" class="input-text" id="calc_shipping_city" name="calc_shipping_city" value="کهک">
        </form>`, { jquery: true });
    const city = document.getElementById('calc_shipping_city');

    assert.equal(city.getAttribute('role'), 'combobox');

    // selectWoo triggers change through jQuery only.
    $('#calc_shipping_state').val('ABZ').trigger('change');

    assert.equal(city.value, '');
    type(window, city, 'ک');
    assert.deepEqual(shown(document, city), ['کرج']);
});

test('the classic form follows WooCommerce\'s redrawn state field', async () => {
    const { $, window, document } = await page(classicHtml('billing', { country: 'DE', state: '' }), { jquery: true });
    const city = document.getElementById('billing_city');
    assert.equal(city.hasAttribute('role'), false);

    // WooCommerce swaps in a new state select, then fires its event.
    $('#billing_country').val('IR');
    $('#billing_state').replaceWith(selectHtml('id="billing_state" name="billing_state"', ['', 'THR'], 'THR'));
    $(window.document.body).trigger('country_to_state_changed', ['IR', $('.woocommerce-billing-fields')]);

    type(window, city, 'تهر');
    assert.deepEqual(shown(document, city), ['تهران']);
});

test('a city that another plugin made a select is left alone', async () => {
    const { document } = await page(`
        <div class="woocommerce-billing-fields">
            ${selectHtml('id="billing_country" autocomplete="country"', ['IR'], 'IR')}
            ${selectHtml('id="billing_state" autocomplete="address-level1"', ['QHM'], 'QHM')}
            ${selectHtml('id="billing_city" autocomplete="address-level2"', ['قم'], 'قم')}
        </div>`);

    const city = document.getElementById('billing_city');
    assert.equal(city.hasAttribute('role'), false);
    assert.equal(city.hasAttribute('data-persian-kit-city'), false);
    assert.equal(document.querySelectorAll('[role="listbox"]').length, 0);
});

test('without the city data the script does nothing', async () => {
    const { document } = await page(blockHtml('billing'), { cities: null });

    assert.equal(document.getElementById('billing-city').hasAttribute('role'), false);
    assert.equal(document.querySelectorAll('[role="listbox"]').length, 0);
});
