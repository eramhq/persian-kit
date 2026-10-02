import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

// The built bundle: Alpine with the settings page components registered.
const source = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');

const TABS = ['display', 'writing', 'integrations', 'tools'];

/** The settings page as AdminPage::render() prints it, cut down. */
function pageHtml(active, seenNonce = '') {
    const tab = (name) => `
        <a href="/wp-admin/admin.php?page=persian-kit&amp;tab=${name}" id="persian-kit-tab-${name}"
            class="nav-tab${name === active ? ' nav-tab-active' : ''}" role="tab"
            aria-selected="${name === active}" aria-controls="persian-kit-panel-${name}" data-tab="${name}">${name}</a>`;
    const panel = (name, body) => `
        <div id="persian-kit-panel-${name}" class="persian-kit-panel" role="tabpanel"
            aria-labelledby="persian-kit-tab-${name}"${name === active ? '' : ' hidden'}>${body}</div>`;

    return `
        <div class="wrap persian-kit-wrap" x-data="persianKitTabs"${seenNonce ? ` data-seen-nonce="${seenNonce}"` : ''}>
            <section id="persian-kit-compatibility"><details id="compat-card"><summary>WP Jalali</summary></details></section>
            <nav class="nav-tab-wrapper" role="tablist">${TABS.map(tab).join('')}</nav>
            <form id="persian-kit-settings-form" method="post" action="options.php">
                <input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=persian-kit&amp;tab=${active}">
                ${panel('display', '<input type="checkbox" id="digits" name="persian_kit_settings[digit_conversion][enabled]" value="1">')}
                ${panel('writing', '<input type="checkbox" id="letters" name="persian_kit_settings[char_normalization][teh_marbuta]" value="1">')}
                ${panel('integrations', '')}
                <div class="persian-kit-savebar"${active === 'tools' ? ' hidden' : ''}>
                    <input type="submit" value="Save">
                    <span class="persian-kit-savebar__status" x-show="dirty" x-cloak>Unsaved changes</span>
                </div>
            </form>
            ${panel('tools', `
                <section x-data="persianKitNormalize({ labels: { post: 'Posts' }, selected: ['post'] })">
                    <input type="checkbox" id="post-type" value="post" x-model="postTypes">
                    <p id="dirty-warning" x-show="settingsDirty">Save first</p>
                </section>`)}
        </div>`;
}

async function page({ active = 'display', dir = 'ltr', search = `?page=persian-kit&tab=${active}&settings-updated=true`, hash = '', seenNonce = '' } = {}) {
    const dom = new JSDOM(`<!doctype html><html dir="${dir}"><body>${pageHtml(active, seenNonce)}</body></html>`, {
        url: `https://example.test/wp-admin/admin.php${search}${hash}`,
        runScripts: 'outside-only',
        // Gives the window requestAnimationFrame, which x-show waits on.
        pretendToBeVisual: true,
    });
    const { window } = dom;

    window.wp = {
        i18n: {
            __: (text) => text,
            _n: (single, plural, count) => (count === 1 ? single : plural),
            sprintf: (format, ...values) => format.replace(/%(\d\$)?[ds]/g, () => values.shift()),
        },
    };
    window.persianKitSettings = { restUrl: 'https://example.test/wp-json/persian-kit/v1/', nonce: 'n' };
    window.fetch = async () => ({ ok: true, json: async () => ({ job: { status: 'idle' } }) });

    window.eval(source);
    await tick(window);

    return window;
}

/** Lets Alpine run its queued effects, then x-show its next frame. */
function tick(window) {
    return new Promise((resolve) => window.setTimeout(() => window.requestAnimationFrame(() => resolve()), 0));
}

function visiblePanels(window) {
    return [...window.document.querySelectorAll('[role="tabpanel"]')]
        .filter((panel) => !panel.hidden)
        .map((panel) => panel.id.replace('persian-kit-panel-', ''));
}

function selectedTabs(window) {
    return [...window.document.querySelectorAll('[role="tab"][aria-selected="true"]')].map((tab) => tab.dataset.tab);
}

function referer(window) {
    return window.document.querySelector('input[name="_wp_http_referer"]').value;
}

function click(window, name, options = {}) {
    const event = new window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options });
    window.document.getElementById(`persian-kit-tab-${name}`).dispatchEvent(event);
    return event;
}

function key(window, name, keyName) {
    const event = new window.KeyboardEvent('keydown', { key: keyName, bubbles: true, cancelable: true });
    window.document.getElementById(`persian-kit-tab-${name}`).dispatchEvent(event);
    return event;
}

test('only the active tab can be reached with Tab', async () => {
    const window = await page({ active: 'writing' });

    const tabIndexes = TABS.map((name) => window.document.getElementById(`persian-kit-tab-${name}`).tabIndex);
    assert.deepEqual(tabIndexes, [-1, 0, -1, -1]);
});

test('clicking a tab shows its panel without leaving the page', async () => {
    const window = await page();

    const event = click(window, 'writing');

    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(visiblePanels(window), ['writing']);
    assert.deepEqual(selectedTabs(window), ['writing']);
    assert.ok(window.document.getElementById('persian-kit-tab-writing').classList.contains('nav-tab-active'));
    assert.ok(!window.document.getElementById('persian-kit-tab-display').classList.contains('nav-tab-active'));
});

test('switching tabs updates the address and where Save returns to', async () => {
    const window = await page();

    click(window, 'integrations');

    assert.equal(window.location.search, '?page=persian-kit&tab=integrations');
    assert.equal(referer(window), '/wp-admin/admin.php?page=persian-kit&tab=integrations');
});

test('a modified click is left to the browser', async () => {
    const window = await page();

    const event = click(window, 'writing', { ctrlKey: true });

    assert.equal(event.defaultPrevented, false);
    assert.deepEqual(visiblePanels(window), ['display']);
});

test('the save bar hides on the Tools tab', async () => {
    const window = await page();
    const saveBar = window.document.querySelector('.persian-kit-savebar');

    click(window, 'tools');
    assert.equal(saveBar.hidden, true);

    click(window, 'display');
    assert.equal(saveBar.hidden, false);
});

test('arrow keys, Home and End move between tabs', async () => {
    const window = await page();
    const focused = () => window.document.activeElement.dataset.tab;

    key(window, 'display', 'ArrowRight');
    assert.equal(focused(), 'writing');
    assert.deepEqual(visiblePanels(window), ['writing']);

    key(window, 'writing', 'End');
    assert.equal(focused(), 'tools');

    key(window, 'tools', 'ArrowRight');
    assert.equal(focused(), 'display', 'wraps around');

    key(window, 'display', 'ArrowLeft');
    assert.equal(focused(), 'tools');

    key(window, 'tools', 'Home');
    assert.equal(focused(), 'display');
    assert.deepEqual(selectedTabs(window), ['display']);
});

test('in right-to-left admin the left arrow moves to the next tab', async () => {
    const window = await page({ dir: 'rtl' });

    key(window, 'display', 'ArrowLeft');
    assert.equal(window.document.activeElement.dataset.tab, 'writing');
});

test('other keys are left alone', async () => {
    const window = await page();

    const event = key(window, 'display', 'a');

    assert.equal(event.defaultPrevented, false);
    assert.deepEqual(visiblePanels(window), ['display']);
});

test('an edit shows "Unsaved changes"', async () => {
    const window = await page();
    const status = window.document.querySelector('.persian-kit-savebar__status');

    assert.equal(status.hasAttribute('x-cloak') || status.style.display === 'none', true);

    window.document.getElementById('digits').click();
    await tick(window);

    assert.equal(status.hasAttribute('x-cloak'), false);
    assert.notEqual(status.style.display, 'none');
});

test('the fix tool sees unsaved changes made on another tab', async () => {
    const window = await page();
    const warning = window.document.getElementById('dirty-warning');

    window.document.getElementById('post-type').click();
    await tick(window);
    assert.equal(warning.style.display, 'none', "the tool's own inputs are not settings");

    window.document.getElementById('letters').click();
    await tick(window);
    assert.notEqual(warning.style.display, 'none');
});

test('the compatibility cards open when the Plugins screen notice links to them', async () => {
    const closed = await page();
    assert.equal(closed.document.getElementById('compat-card').open, false);

    const linked = await page({ hash: '#persian-kit-compatibility' });
    assert.equal(linked.document.getElementById('compat-card').open, true);
});

test('opening the Integrations tab tells the server once that its New cards were seen', async () => {
    const window = await page({ seenNonce: 'seen-nonce' });
    const requests = [];
    window.ajaxurl = '/wp-admin/admin-ajax.php';
    window.fetch = async (url, options) => {
        requests.push([url, Object.fromEntries(options.body)]);
        return { ok: true };
    };

    click(window, 'writing');
    assert.equal(requests.length, 0);

    click(window, 'integrations');
    click(window, 'display');
    click(window, 'integrations');

    assert.deepEqual(requests, [
        ['/wp-admin/admin-ajax.php', { action: 'persian_kit_seen_integrations', _ajax_nonce: 'seen-nonce', tab: 'integrations' }],
    ]);
});

test('without unseen integrations nothing is sent', async () => {
    const window = await page();
    let sent = 0;
    window.ajaxurl = '/wp-admin/admin-ajax.php';
    window.fetch = async () => {
        sent++;
        return { ok: true };
    };

    click(window, 'integrations');

    assert.equal(sent, 0);
});

test('a link to the compatibility cards opens them', async () => {
    const window = await page();
    const card = window.document.getElementById('compat-card');
    assert.equal(card.open, false);

    window.location.hash = '#persian-kit-compatibility';
    await new Promise((resolve) => window.addEventListener('hashchange', () => resolve(), { once: true }));

    assert.equal(card.open, true);
});
