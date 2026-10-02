import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const source = readFileSync(new URL('../../resources/js/woocommerce-block-prices.js', import.meta.url), 'utf8');

const PRICE = 'wc-block-components-formatted-money-amount';
const PRODUCT_PRICE = 'wc-block-components-product-price';

/** A page with the script loaded, after it has run once. */
async function page(body) {
    const dom = new JSDOM(`<!doctype html><html><body>${body}</body></html>`, { runScripts: 'outside-only' });
    dom.window.eval(source);
    // jsdom finishes loading asynchronously; the script waits for DOMContentLoaded.
    if (dom.window.document.readyState !== 'complete') {
        await new Promise((resolve) => dom.window.addEventListener('load', resolve, { once: true }));
    }
    await settle(dom.window);

    return dom.window;
}

/** Lets DOMContentLoaded and pending MutationObserver callbacks run. */
function settle(window) {
    return new Promise((resolve) => window.setTimeout(resolve, 0));
}

test('digits inside price elements become Persian, other digits stay', async () => {
    const window = await page(`
        <span class="${PRICE}">150.000,00 €</span>
        <div class="${PRODUCT_PRICE}"><del>200</del> <ins>150</ins></div>
        <span class="quantity">2 items</span>
        <input class="${PRICE}-input" value="2">
    `);
    const { document } = window;

    assert.equal(document.querySelector(`.${PRICE}`).textContent, '۱۵۰.۰۰۰,۰۰ €');
    assert.equal(document.querySelector(`.${PRODUCT_PRICE}`).textContent, '۲۰۰ ۱۵۰');
    assert.equal(document.querySelector('.quantity').textContent, '2 items');
    assert.equal(document.querySelector('input').value, '2');
});

test('prices the blocks add later are converted', async () => {
    const window = await page('<div id="cart"></div>');
    const { document } = window;

    document.getElementById('cart').innerHTML = `<p>Total <span class="${PRICE}">300 €</span> for 2 items</p>`;
    await settle(window);

    assert.equal(document.getElementById('cart').textContent, 'Total ۳۰۰ € for 2 items');
});

test('a price whose text React changes is converted again', async () => {
    const window = await page(`<span class="${PRICE}">150 €</span>`);
    const price = window.document.querySelector(`.${PRICE}`);

    // React updates a text node in place when the quantity changes.
    price.firstChild.nodeValue = '300 €';
    await settle(window);

    assert.equal(price.textContent, '۳۰۰ €');
});

test('a price React re-renders with new children is converted', async () => {
    const window = await page(`<span class="${PRICE}"><span>150</span> €</span>`);
    const price = window.document.querySelector(`.${PRICE}`);

    price.innerHTML = '<span>450</span> €';
    await settle(window);

    assert.equal(price.textContent, '۴۵۰ €');
});

test('converting does not set off another round of changes', async () => {
    const window = await page(`<span class="${PRICE}">150 €</span>`);
    const price = window.document.querySelector(`.${PRICE}`);
    const records = [];
    new window.MutationObserver((list) => records.push(...list))
        .observe(price, { characterData: true, childList: true, subtree: true });

    price.firstChild.nodeValue = '300 €';
    await settle(window);
    await settle(window);

    // One change from the test, one from the script, then quiet.
    assert.equal(records.length, 2);
    assert.equal(price.textContent, '۳۰۰ €');
});

test('a price already in Persian digits is not written to', async () => {
    const window = await page(`<span class="${PRICE}">۱۵۰ €</span>`);
    const price = window.document.querySelector(`.${PRICE}`);
    const records = [];
    new window.MutationObserver((list) => records.push(...list))
        .observe(price, { characterData: true, childList: true, subtree: true });

    price.appendChild(window.document.createTextNode(' تومان'));
    await settle(window);

    assert.equal(records.length, 1);
    assert.equal(price.textContent, '۱۵۰ € تومان');
});
