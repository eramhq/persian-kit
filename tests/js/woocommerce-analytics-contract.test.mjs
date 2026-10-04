import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';
import { fileURLToPath } from 'node:url';

// What woocommerce-analytics-dates.js reads from WooCommerce's own scripts,
// checked against the WooCommerce release in PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR
// (CI runs it with the oldest and latest supported ones) or next to the
// plugin. Uses Node's built-in modules only: CI's integration job has no
// npm packages. If this fails, a WooCommerce release changed something the
// script replaces, and Analytics would fall back to Gregorian.
const wooCommerceDir = process.env.PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR
    || fileURLToPath(new URL('../../../woocommerce', import.meta.url));
const admin = `${wooCommerceDir}/assets/client/admin`;
// Where CI names a WooCommerce, a missing date package fails the test.
const skip = existsSync(`${admin}/date/index.js`) || process.env.PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR
    ? false
    : 'WooCommerce is not installed next to the plugin.';

const source = readFileSync(new URL('../../resources/js/woocommerce-analytics-dates.js', import.meta.url), 'utf8');

/** A WooCommerce package evaluated as the page would, with stand-ins for its dependencies. */
function load(path, global) {
    // The CSV package's file saver looks at these when it loads.
    const window = createContext({ console, navigator: { userAgent: 'node' }, document: { createElementNS: () => ({}) }, ...global });
    window.window = window;
    window.moment = Object.assign(() => ({}), { isMoment: () => false, locale: () => 'en' });
    window.lodash = { memoize: (fn) => fn, find: (list, predicate) => list.find(predicate) };
    window.wp = { i18n: { __: (text) => text, _x: (text) => text, sprintf: (text) => text } };
    runInContext(readFileSync(`${admin}/${path}`, 'utf8'), window);

    return window;
}

test('wc.date has every member the Jalali one reads', { skip }, () => {
    const date = load('date/index.js').wc.date;
    const contract = JSON.parse(/var CONTRACT = (\[[^\]]*\]);/.exec(source)[1].replace(/'/g, '"').replace(/,\s*\]/, ']'));

    assert.ok(contract.length >= 5);
    for (const name of contract) {
        assert.equal(typeof date[name], 'function', name);
    }
    // Replaced too; the screens read them.
    for (const name of ['getCurrentPeriod', 'getLastPeriod', 'getRangeLabel', 'getAllowedIntervalsForQuery', 'getIntervalForQuery']) {
        assert.equal(typeof date[name], 'function', name);
    }
    // The presets and comparisons the Jalali periods answer for.
    const plain = (value) => JSON.parse(JSON.stringify(value));
    assert.deepEqual(
        plain(date.presetValues.map((preset) => preset.value)),
        ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'quarter', 'last_quarter', 'year', 'last_year', 'custom']
    );
    assert.deepEqual(plain(date.periods.map((period) => period.value)), ['previous_period', 'previous_year']);
    // The chart's formats come as PHP formats, which the wrapped wp.date reads.
    assert.equal(typeof date.getDateFormatsForInterval('month', 12, { type: 'php' }).xFormat, 'string');
});

/** The Analytics app with the chunks it loads later. */
function app() {
    const chunks = existsSync(`${admin}/chunks`) ? readdirSync(`${admin}/chunks`).filter((file) => file.endsWith('.js')) : [];

    return [readFileSync(`${admin}/app/index.js`, 'utf8'), ...chunks.map((file) => readFileSync(`${admin}/chunks/${file}`, 'utf8'))].join('\n');
}

test('WooCommerce\'s screens read wc.date and wc.csvExport when they load, not before', { skip }, () => {
    const analytics = app();

    assert.match(analytics, /window\.wc\.date\b/);
    for (const path of ['components/index.js', 'data/index.js']) {
        assert.match(readFileSync(`${admin}/${path}`, 'utf8'), /window\.wc\.date\b/, path);
    }
    assert.match(analytics, /window\.wc\.csvExport\b/);
    // The report tables filter, and the stats requests by interval.
    assert.match(analytics, /woocommerce_admin_report_table/);
    assert.match(readFileSync(`${admin}/data/index.js`, 'utf8'), /\/reports\/\$\{[A-Za-z_$]+\}\/stats/);
});

test('the CSV builder and the custom range calendar are where the script looks', { skip }, () => {
    const csv = load('csv-export/index.js').wc.csvExport;
    assert.equal(typeof csv.generateCSVDataFromTable, 'function');
    assert.equal(csv.generateCSVDataFromTable([{ label: 'Date' }], [[{ value: '2025-10-01' }]]), 'Date\n2025-10-01');

    const components = readFileSync(`${admin}/components/index.js`, 'utf8');
    for (const name of ['woocommerce-calendar', 'woocommerce-calendar__inputs', 'woocommerce-calendar__react-dates']) {
        assert.ok(components.includes(`"${name}"`), name);
    }
});
