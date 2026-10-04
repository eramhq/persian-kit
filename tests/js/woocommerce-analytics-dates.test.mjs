import { test, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { createContext, runInContext } from 'node:vm';

const require = createRequire(import.meta.url);
const moment = require('moment-timezone');

const jalaliSource = readFileSync(new URL('../../resources/js/jalali.js', import.meta.url), 'utf8');
const source = readFileSync(new URL('../../resources/js/woocommerce-analytics-dates.js', import.meta.url), 'utf8');

// WooCommerce's own date package when it sits next to the plugin (or in
// PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR), else a stand-in with the same members.
const wooCommerceDir = process.env.PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR
    || new URL('../../../woocommerce', import.meta.url).pathname;
const wooDatePath = `${wooCommerceDir}/assets/client/admin/date/index.js`;
const wooDateSource = existsSync(wooDatePath) ? readFileSync(wooDatePath, 'utf8') : null;

afterEach(() => {
    moment.now = () => Date.now();
});

const lodash = {
    memoize(fn, resolver) {
        const cache = new Map();
        return (...args) => {
            const key = resolver ? resolver(...args) : args[0];
            if (!cache.has(key)) {
                cache.set(key, fn(...args));
            }
            return cache.get(key);
        };
    },
    find: (list, predicate) => list.find(predicate),
};

/** The members of WooCommerce's wc.date that the script reads (WooCommerce 10.6.2). */
function wooDateStandIn(window) {
    const presetValues = ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'quarter', 'last_quarter', 'year', 'last_year', 'custom']
        .map((value) => ({ value, label: value }));
    const periods = [{ value: 'previous_period', label: 'Previous period' }, { value: 'previous_year', label: 'Previous year' }];
    const date = {
        presetValues,
        periods,
        dayTicksThreshold: 63,
        weekTicksThreshold: 9,
        defaultTableDateFormat: 'm/d/Y',
        appendTimestamp: (m, which) => (which === 'start' ? m.startOf('day') : m.endOf('day')).format('YYYY-MM-DDTHH:mm:ss'),
        getStoreTimeZoneMoment() {
            const timeZone = window.wcSettings?.timeZone;
            return timeZone ? window.moment().tz(timeZone) : window.moment();
        },
        getDateParamsFromQuery(query, defaultDateRange = 'period=month&compare=previous_year') {
            const { period, compare, after, before } = query;
            if (period && compare) {
                return { period, compare, after: after ? window.moment(after) : null, before: before ? window.moment(before) : null };
            }
            const params = new URLSearchParams(defaultDateRange);
            return {
                period: params.get('period') || '',
                compare: params.get('compare') || '',
                after: params.get('after') ? window.moment(params.get('after')) : null,
                before: params.get('before') ? window.moment(params.get('before')) : null,
            };
        },
        getCurrentDates() {
            throw new Error('Replaced');
        },
        getPreviousDate(value, date1, date2, compare = 'previous_year', interval) {
            const m = window.moment(value);
            if (compare === 'previous_year') {
                return m.clone().subtract(1, 'years');
            }
            return m.clone().subtract(window.moment(date1).diff(window.moment(date2), interval), interval);
        },
        getDateFormatsForInterval: () => ({ xFormat: '%Y-%m-%d' }),
        getDateFormatsForIntervalPhp: () => ({ xFormat: 'Y-m-d' }),
        containsLeapYear: () => true,
    };
    return date;
}

const PHP_TO_MOMENT = { Y: 'YYYY', y: 'YY', m: 'MM', n: 'M', d: 'DD', j: 'D', F: 'MMMM', M: 'MMM', D: 'ddd', l: 'dddd', H: 'HH', G: 'H', h: 'hh', g: 'h', i: 'mm', s: 'ss', A: 'A', a: 'a', N: 'E', w: 'd' };

/** wp.date's formatters for PHP formats, Gregorian, in English; date() and dateI18n() in the site's time zone. */
function wpDateStandIn(timeZone) {
    const formatMoment = (format, m) => {
        let out = '';
        for (let i = 0; i < format.length; i++) {
            const token = format[i];
            if (token === '\\') {
                out += format[++i] ?? '';
            } else {
                out += PHP_TO_MOMENT[token] ? m.format(PHP_TO_MOMENT[token]) : token;
            }
        }
        return out;
    };
    const site = (value, zone) => moment.tz(value ?? moment.now(), zone || timeZone);
    return {
        setSettings() {},
        format: (format, value) => formatMoment(format, moment(value ?? moment.now())),
        date: (format, value, zone) => formatMoment(format, site(value, zone)),
        dateI18n: (format, value, zone) => formatMoment(format, site(value, zone)),
    };
}

/**
 * A page at the given moment (UTC), store time zone Tehran unless set, with
 * WooCommerce's date package and this script installed.
 */
function page(now, { timeZone = 'Asia/Tehran', startOfWeek = 6, date } = {}) {
    moment.now = () => new Date(now).getTime();

    const window = createContext({ console, Object, Array, Math, Date, URLSearchParams });
    window.window = window;
    window.moment = moment;
    window.lodash = lodash;
    window.wp = { i18n: { __: (text) => text } };
    window.wcSettings = { timeZone };
    window.persianKitAnalyticsDates = { startOfWeek, labels: { weekOf: 'هفته' } };
    window.wp.date = wpDateStandIn(timeZone);

    if (date) {
        window.wc = { date };
    } else if (wooDateSource) {
        runInContext(wooDateSource, window);
    } else {
        window.wc = { date: wooDateStandIn(window) };
    }

    runInContext(jalaliSource, window);
    runInContext(source, window);
    // In the order the page loads them: wp.date, then wc.date.
    window.PersianKitAnalyticsDates.installWpDate();
    window.gregorianBeforeWcDate = window.wp.date.format('j F Y', '2025-10-01 00:00:00');
    window.installed = window.PersianKitAnalyticsDates.installWcDate();

    return window;
}

const day = (m) => m.format('YYYY-MM-DD');

function range(window, query) {
    const { primary, secondary } = window.wc.date.getCurrentDates(query);
    return {
        primary: [day(primary.after), day(primary.before)],
        secondary: [day(secondary.after), day(secondary.before)],
        label: primary.range,
        secondaryLabel: secondary.range,
    };
}

// 9 Mehr 1404 is 1 October 2025, noon in Tehran.
const MEHR_9 = '2025-10-01T08:30:00Z';

test('wc.date is replaced and the original kept as it was', () => {
    const window = page(MEHR_9);

    assert.equal(window.installed, true);
    assert.equal(typeof window.wc.date.getCurrentDates, 'function');
    assert.equal(window.wc.date.isoDateFormat ?? 'YYYY-MM-DD', 'YYYY-MM-DD');
    assert.equal(window.PersianKitAnalyticsDates.installWcDate(), false, 'only once');
});

test('last month is the whole Jalali month before (1)', () => {
    const window = page(MEHR_9);

    assert.deepEqual(range(window, { period: 'last_month', compare: 'previous_year' }), {
        primary: ['2025-08-23', '2025-09-22'],
        secondary: ['2024-08-22', '2024-09-21'],
        label: '1 - 31 شهریور 1404',
        secondaryLabel: '1 - 31 شهریور 1403',
    });

    // Previous period: the whole month before that.
    assert.deepEqual(range(window, { period: 'last_month', compare: 'previous_period' }).secondary, ['2025-07-23', '2025-08-22']);
});

test('this quarter is the Jalali season so far (2)', () => {
    const window = page(MEHR_9);
    const dates = range(window, { period: 'quarter', compare: 'previous_period' });

    assert.deepEqual(dates.primary, ['2025-09-23', '2025-10-01']);
    assert.equal(dates.label, '1 - 9 مهر 1404');
    assert.deepEqual(dates.secondary, ['2025-06-22', '2025-06-30'], '1 - 9 Tir');

    // Last quarter: the summer.
    assert.deepEqual(range(window, { period: 'last_quarter', compare: 'previous_year' }).primary, ['2025-06-22', '2025-09-22']);
});

test('year to date starts on 1 Farvardin (3)', () => {
    const window = page(MEHR_9);
    const dates = range(window, { period: 'year', compare: 'previous_year' });

    assert.deepEqual(dates.primary, ['2025-03-21', '2025-10-01']);
    assert.equal(dates.label, '1 فروردین - 9 مهر 1404');
    // As many days from 1 Farvardin 1403.
    assert.deepEqual(dates.secondary, ['2024-03-20', '2024-09-30']);
});

test('last year is the whole Jalali year, with 30 Esfand in a leap year (4)', () => {
    const window = page(MEHR_9);
    const dates = range(window, { period: 'last_year', compare: 'previous_year' });

    assert.deepEqual(dates.primary, ['2024-03-20', '2025-03-20']);
    assert.equal(dates.label, '1 فروردین - 30 اسفند 1403');
    assert.deepEqual(dates.secondary, ['2023-03-21', '2024-03-19'], '1402 has 29 days in Esfand');
});

test('30 Esfand of a leap year compares with 29 Esfand a year back (5)', () => {
    const window = page(MEHR_9);
    const dates = range(window, { period: 'custom', compare: 'previous_year', after: '2025-03-20', before: '2025-03-20' });

    assert.deepEqual(dates.primary, ['2025-03-20', '2025-03-20']);
    assert.equal(dates.label, '30 اسفند 1403');
    assert.deepEqual(dates.secondary, ['2024-03-19', '2024-03-19']);
});

test('a custom range compared with the previous period keeps its length, as in WooCommerce', () => {
    const window = page(MEHR_9);
    const dates = range(window, { period: 'custom', compare: 'previous_period', after: '2025-03-05', before: '2025-04-04' });

    assert.equal(dates.label, '15 اسفند 1403 - 15 فروردین 1404');
    assert.deepEqual(dates.secondary, ['2025-02-02', '2025-03-04']);
});

test('the day is the store\'s, not the browser\'s (7)', () => {
    // 23:50 on 31 Shahrivar in Tehran, already 00:20 on 1 Mehr in Dubai.
    const process = { env: { TZ: 'Asia/Dubai' } };
    const window = page('2025-09-22T20:20:00Z');

    assert.equal(moment.tz(moment.now(), process.env.TZ).format('YYYY-MM-DD'), '2025-09-23');
    assert.deepEqual(range(window, { period: 'month', compare: 'previous_year' }).primary, ['2025-08-23', '2025-09-22']);
    assert.deepEqual(range(page('2025-09-22T20:50:00Z'), { period: 'month', compare: 'previous_year' }).primary, ['2025-09-23', '2025-09-23']);
});

test('weeks start on the site\'s first day of the week (8)', () => {
    // 1 October 2025 is a Wednesday.
    assert.deepEqual(range(page(MEHR_9, { startOfWeek: 6 }), { period: 'week', compare: 'previous_period' }).primary, ['2025-09-27', '2025-10-01']);
    assert.deepEqual(range(page(MEHR_9, { startOfWeek: 1 }), { period: 'week', compare: 'previous_period' }).primary, ['2025-09-29', '2025-10-01']);
    assert.deepEqual(range(page(MEHR_9, { startOfWeek: 6 }), { period: 'last_week', compare: 'previous_period' }), {
        primary: ['2025-09-20', '2025-09-26'],
        secondary: ['2025-09-13', '2025-09-19'],
        label: '29 شهریور - 4 مهر 1404',
        secondaryLabel: '22 - 28 شهریور 1404',
    });
});

test('month to date compares with the same days of the month before (9)', () => {
    const window = page(MEHR_9);

    assert.deepEqual(range(window, { period: 'month', compare: 'previous_period' }), {
        primary: ['2025-09-23', '2025-10-01'],
        secondary: ['2025-08-23', '2025-08-31'],
        label: '1 - 9 مهر 1404',
        secondaryLabel: '1 - 9 شهریور 1404',
    });
});

test('a bookmarked period follows the day it is opened (10)', () => {
    // 10 Aban 1404.
    const window = page('2025-11-01T08:30:00Z');

    assert.deepEqual(range(window, { period: 'last_month', compare: 'previous_year' }).primary, ['2025-09-23', '2025-10-22']);
});

test('the default date range applies without period in the URL', () => {
    const window = page(MEHR_9);

    assert.deepEqual(range(window, {}).primary, ['2025-09-23', '2025-10-01'], 'month to date');
    assert.deepEqual(window.wc.date.getCurrentDates({}, 'period=last_quarter&compare=previous_year').primary.after.format('YYYY-MM-DD'), '2025-06-22');
});

test('the last season before Nowruz is the winter, 1 Dey to 30 Esfand', () => {
    // 1 Farvardin 1404.
    const window = page('2025-03-21T08:30:00Z');

    assert.deepEqual(range(window, { period: 'last_quarter', compare: 'previous_year' }), {
        primary: ['2024-12-21', '2025-03-20'],
        secondary: ['2023-12-22', '2024-03-19'],
        label: '1 دی - 30 اسفند 1403',
        secondaryLabel: '1 دی - 29 اسفند 1402',
    });
    assert.deepEqual(range(window, { period: 'quarter', compare: 'previous_period' }).primary, ['2025-03-21', '2025-03-21']);
});

test('the same query gives the same object, so the screens see no change', () => {
    const window = page(MEHR_9);
    const query = { period: 'last_month', compare: 'previous_year' };

    assert.equal(window.wc.date.getCurrentDates(query), window.wc.date.getCurrentDates({ ...query }));
    assert.throws(() => window.wc.date.getCurrentDates({ period: 'fortnight', compare: 'previous_year' }), /Cannot find period/);
});

test('the compared date in the tooltip moves by Jalali months and years', () => {
    const window = page(MEHR_9);
    const { getPreviousDate } = window.wc.date;
    const format = (m) => m.format('YYYY-MM-DD HH:mm:ss');

    // 1 Mehr 1404 a Jalali year back.
    assert.equal(format(getPreviousDate('2025-09-23 00:00:00', '2025-09-23', '2024-09-22', 'previous_year', 'month')), '2024-09-22 00:00:00');
    // Previous period by month: Mehr against Shahrivar.
    assert.equal(format(getPreviousDate('2025-09-23 00:00:00', '2025-09-23', '2025-08-23', 'previous_period', 'month')), '2025-08-23 00:00:00');
    // By season: autumn against summer.
    assert.equal(format(getPreviousDate('2025-10-23 00:00:00', '2025-09-23', '2025-06-22', 'previous_period', 'quarter')), '2025-07-23 00:00:00');
    // By day, as WooCommerce does.
    assert.equal(format(getPreviousDate('2025-09-25 00:00:00', '2025-09-23', '2025-09-13', 'previous_period', 'day')), '2025-09-15 00:00:00');
});

test('the chart formats put the day before the month', () => {
    const window = page(MEHR_9);
    const { getDateFormatsForInterval } = window.wc.date;

    assert.deepEqual(
        { ...getDateFormatsForInterval('month', 12, { type: 'php' }) },
        { screenReaderFormat: 'F Y', tooltipLabelFormat: 'F Y', xFormat: 'F', x2Format: 'Y', tableFormat: 'm/d/Y' }
    );
    assert.equal(getDateFormatsForInterval('day', 10, { type: 'php' }).tooltipLabelFormat, 'j F Y');
    assert.equal(getDateFormatsForInterval('day', 90, { type: 'php' }).xFormat, 'F');
    assert.equal(getDateFormatsForInterval('week', 4, { type: 'php' }).tooltipLabelFormat, 'هفته j F Y');
    // d3 formats stay WooCommerce's.
    assert.notEqual(getDateFormatsForInterval('month', 12).xFormat, 'F');
    // The chart's 29 February fix would misplace Jalali years.
    assert.equal(window.wc.date.containsLeapYear('2028-01-01', '2028-12-31'), false);
});

test('a wc.date of another shape is left alone, and dates stay Gregorian (13)', () => {
    const changed = { getCurrentDates: () => 'woo', presetValues: [], periods: [] };
    const window = page(MEHR_9, { date: changed });

    assert.equal(window.installed, false);
    assert.equal(window.wc.date, changed);
    assert.equal(window.wp.date.format('j F Y', '2025-10-01 00:00:00'), '1 October 2025');
});

test('dates printed for people are Jalali (B)', () => {
    const window = page(MEHR_9);
    const { format, dateI18n } = window.wp.date;

    assert.equal(window.gregorianBeforeWcDate, '1 October 2025', 'Gregorian until wc.date is replaced');
    assert.equal(format('j F Y', '2025-10-01 00:00:00'), '9 مهر 1404');
    assert.equal(format('F Y', '2025-09-23 00:00:00'), 'مهر 1404');
    assert.equal(format('Q Y', '2025-12-22 00:00:00'), 'زمستان 1404');
    assert.equal(format('Q', '2025-03-21 00:00:00'), 'بهار');
    assert.equal(format('Y/m/d', '2025-10-01 12:34:56'), '1404/07/09');
    assert.equal(format('l j F Y', '2025-10-01'), 'چهارشنبه 9 مهر 1404');
    // Time tokens come from wp.date itself.
    assert.equal(format('gA j F Y', '2025-10-01 15:00:00'), '3PM 9 مهر 1404');
    assert.equal(format('H:i', '2025-10-01 15:04:00'), '15:04');
    assert.equal(format('\\W\\e\\e\\k j', '2025-10-01'), 'Week 9');
    assert.equal(format('هفته j F Y', '2025-10-01'), 'هفته 9 مهر 1404');
    // In the time zone wp.date uses: 00:20 on 1 Mehr in Tehran.
    assert.equal(dateI18n('j F Y', '2025-09-22T20:50:00Z'), '1 مهر 1404');
    assert.equal(typeof window.wp.date.setSettings, 'function');
});

test('formats machines read stay Gregorian (11)', () => {
    const window = page(MEHR_9);
    const { format } = window.wp.date;

    // The link from the Revenue table to the orders of that day.
    assert.equal(format('Ymd', '2025-10-01 00:00:00'), '20251001');
    // The chart's keys.
    assert.equal(format('Y-m-d\\TH:i:s', '2025-10-01 00:00:00'), '2025-10-01T00:00:00');
    assert.equal(format('Y-m-d', '2025-10-01'), '2025-10-01');
    assert.equal(format('Y-m-d H:i:s', '2025-10-01 08:00:00'), '2025-10-01 08:00:00');
    for (const machine of ['U', 'c', 'D, d M Y H:i:s O', 'Y-m-d\\TH:i:sP']) {
        assert.equal(window.PersianKitAnalyticsDates.isMachineFormat(machine), true, machine);
    }
    assert.equal(window.PersianKitAnalyticsDates.isMachineFormat('Y/m/d'), false);
});

test('stats by month, season or year ask the server for Jalali periods (C)', () => {
    const window = page(MEHR_9);
    const { withFlag } = window.PersianKitAnalyticsDates;
    const path = '/wc-analytics/reports/revenue/stats?order=asc&interval=month&per_page=100&after=2025-03-21T00%3A00%3A00';

    assert.equal(withFlag({ path }).path, path + '&persian_kit_calendar=jalali');
    assert.equal(withFlag({ path: path.replace('month', 'quarter') }).path.endsWith('&persian_kit_calendar=jalali'), true);
    assert.equal(withFlag({ path: '/wc-analytics/reports/pk-extension/stats?interval=year' }).path, '/wc-analytics/reports/pk-extension/stats?interval=year&persian_kit_calendar=jalali');
    // Plain permalinks.
    const url = 'http://example.com/?rest_route=%2Fwc-analytics%2Freports%2Forders%2Fstats&interval=month';
    assert.equal(withFlag({ url }).url, url + '&persian_kit_calendar=jalali');

    // Days, weeks, other reports and other endpoints go as they are.
    for (const other of [
        path.replace('month', 'day'),
        path.replace('month', 'week'),
        '/wc-analytics/reports/revenue?interval=month',
        '/wc-analytics/leaderboards?after=2025-03-21',
        '/wp/v2/posts?interval=month',
    ]) {
        const options = { path: other };
        assert.equal(withFlag(options), options, other);
    }
});

test('the flag is added only once wc.date is Jalali', async () => {
    const seen = [];
    const middlewares = [];
    const apiFetch = {
        use: (middleware) => middlewares.push(middleware),
        run: (options) => middlewares[0](options, (final) => seen.push(final.path)),
    };
    const path = '/wc-analytics/reports/revenue/stats?interval=month';

    const jalali = page(MEHR_9);
    jalali.wp.apiFetch = apiFetch;
    assert.equal(jalali.PersianKitAnalyticsDates.installApiFetch(), true);
    apiFetch.run({ path });

    const gregorian = page(MEHR_9, { date: { presetValues: [], periods: [] } });
    gregorian.wp.apiFetch = { ...apiFetch, use: (middleware) => { middlewares[0] = middleware; } };
    gregorian.PersianKitAnalyticsDates.installApiFetch();
    apiFetch.run({ path });

    assert.deepEqual(seen, [path + '&persian_kit_calendar=jalali', path]);
});

/**
 * WooCommerce's custom range calendar as WooCommerce 10.6.2 renders it, in a
 * page with this script; the inputs record what React would read.
 */
async function customRange(after = '03/06/2026', before = '04/04/2026') {
    const { JSDOM } = await import('jsdom');
    const dom = new JSDOM('<!doctype html><html lang="fa" dir="rtl"><body></body></html>', { runScripts: 'outside-only' });
    const { window } = dom;

    moment.now = () => new Date(MEHR_9).getTime();
    window.moment = moment;
    window.wp = { date: wpDateStandIn('Asia/Tehran') };
    window.wcSettings = { timeZone: 'Asia/Tehran' };
    window.persianKitAnalyticsDates = { startOfWeek: 6, labels: {} };
    window.persianKitDateField = { locale: 'fa-IR' };
    window.wc = { date: wooDateStandIn(window) };
    window.eval(jalaliSource);
    window.eval(source);
    window.PersianKitAnalyticsDates.installWcDate();

    window.document.body.innerHTML = `
        <div class="components-popover woocommerce-filters-date__content">
            <div class="woocommerce-calendar">
                <div class="woocommerce-calendar__inputs">
                    <div class="woocommerce-calendar__input"><input type="text" class="woocommerce-calendar__input-text" value="${after}" placeholder="mm/dd/yyyy" aria-label="Start Date"></div>
                    <div class="woocommerce-calendar__inputs-to">to</div>
                    <div class="woocommerce-calendar__input"><input type="text" class="woocommerce-calendar__input-text" value="${before}" placeholder="mm/dd/yyyy" aria-label="End Date"></div>
                </div>
                <div class="woocommerce-calendar__react-dates"><div class="DayPicker"></div></div>
            </div>
        </div>`;
    await new Promise((resolve) => window.setTimeout(resolve, 0));

    const inputs = [...window.document.querySelectorAll('.woocommerce-calendar__inputs input')];
    const typed = [];
    inputs.forEach((input, index) => input.addEventListener('input', () => typed.push([index ? 'before' : 'after', input.value])));
    const picker = window.document.querySelector('intl-datepicker');
    const pick = (value) => picker.dispatchEvent(new window.CustomEvent('intl-change', { detail: { value } }));

    return { window, inputs, picker, typed, pick };
}

test('custom ranges are picked on a Jalali calendar, written into WooCommerce\'s fields (D)', async () => {
    const { window, inputs, picker, typed, pick } = await customRange();

    assert.ok(picker, 'the calendar is in place');
    assert.equal(window.document.querySelector('.woocommerce-calendar').hasAttribute('data-persian-kit-jalali'), true);
    assert.equal(picker.nextElementSibling.className, 'woocommerce-calendar__react-dates');
    assert.equal(picker.getAttribute('type'), 'range');
    assert.equal(picker.getAttribute('calendar'), 'persian');
    assert.equal(picker.getAttribute('first-day-of-week'), '6');
    assert.equal(picker.getAttribute('numerals'), 'latn');
    assert.equal(picker.hasAttribute('disable-future'), true);
    // 15 Esfand 1404 to 15 Farvardin 1405, read from WooCommerce's fields.
    assert.equal(picker.getAttribute('value'), '2026-03-06/2026-04-04');

    // A start alone waits for the end.
    pick('2026-03-13');
    assert.deepEqual(typed, []);

    // 22 Esfand 1404 to 10 Farvardin 1405, across Nowruz.
    pick('2026-03-13/2026-03-30');
    assert.deepEqual(inputs.map((input) => input.value), ['03/13/2026', '03/30/2026']);
    assert.deepEqual(typed, [['after', '03/13/2026'], ['before', '03/30/2026']]);
});

test('a range after the old one writes the end first, so the start is never after it', async () => {
    const { inputs, typed, pick } = await customRange('03/06/2026', '04/04/2026');

    pick('2026-05-01/2026-06-01');

    assert.deepEqual(typed, [['before', '06/01/2026'], ['after', '05/01/2026']]);
    assert.deepEqual(inputs.map((input) => input.value), ['05/01/2026', '06/01/2026']);
});

test('the fields keep the order their placeholder names', async () => {
    const { window, inputs, pick } = await customRange('06/03/2026', '04/04/2026');
    inputs.forEach((input) => input.setAttribute('placeholder', 'dd/mm/yyyy'));

    pick('2026-03-13/2026-03-30');

    assert.deepEqual(inputs.map((input) => input.value), ['13/03/2026', '30/03/2026']);
    assert.equal(window.document.querySelectorAll('intl-datepicker').length, 1, 'once per calendar');
});

test('focus lost to a redrawn day stays in the popover; focus that leaves reaches it', async () => {
    const { window, picker } = await customRange();
    const seen = [];
    window.document.body.addEventListener('focusout', (event) => seen.push(event.persianKitRetold ? 'retold' : 'lost'));

    const root = picker.attachShadow({ mode: 'open' });
    root.innerHTML = '<button part="day" tabindex="0">22</button><button part="day" tabindex="-1">23</button>';
    const trusted = (target) => target.dispatchEvent(new window.FocusEvent('focusout', { bubbles: true, composed: true }));

    // The day that had focus is redrawn.
    const day = root.querySelector('button');
    trusted(day);
    day.remove();
    await new Promise((resolve) => window.setTimeout(resolve, 5));
    assert.deepEqual(seen, []);
    assert.equal(root.activeElement?.textContent, '23');

    // Focus goes nowhere from a day still there: the popover hears of it.
    trusted(root.querySelector('button'));
    await new Promise((resolve) => window.setTimeout(resolve, 5));
    assert.deepEqual(seen, ['retold']);
});

test('CSV files from the browser get a Jalali column after each date column (E)', () => {
    const window = page(MEHR_9);
    window.persianKitAnalyticsDates.labels.jalaliColumn = '%s (شمسی)';
    const generated = [];
    window.wc.csvExport = {
        generateCSVDataFromTable: (headers, rows) => {
            generated.push({ headers, rows });
            return headers.map((header) => header.label).join(',') + '\n' + rows.map((row) => row.map((cell) => cell.value).join(',')).join('\n');
        },
        downloadCSVFile() {},
    };

    assert.equal(window.PersianKitAnalyticsDates.installCsvExport(), true);

    // The Revenue table: its date cells hold date_start.
    const csv = window.wc.csvExport.generateCSVDataFromTable(
        [{ key: 'date', label: 'Date' }, { key: 'orders_count', label: 'Orders' }],
        [
            [{ display: '9 مهر 1404', value: '2025-10-01 00:00:00' }, { display: '2', value: 2 }],
            [{ display: '10 مهر 1404', value: '2025-10-02 00:00:00' }, { display: '0', value: 0 }],
        ]
    );
    assert.equal(csv, 'Date,Date (شمسی),Orders\n2025-10-01 00:00:00,1404/07/09,2\n2025-10-02 00:00:00,1404/07/10,0');
});

test('only columns of dates count, and empty cells stay empty', () => {
    const window = page(MEHR_9);
    const { addJalaliColumns } = window.PersianKitAnalyticsDates;
    const table = addJalaliColumns(
        [{ key: 'registered', label: 'Sign up' }, { key: 'sku', label: 'SKU' }, { key: 'last', label: 'Last active' }],
        [
            [{ value: '2025-03-20 10:00:00' }, { value: '2025-03-20' }, { value: '' }],
            [{ value: '2025-03-21T09:00:00' }, { value: 'PK-1' }, { value: '2024-03-19' }],
        ]
    );

    const plainData = (value) => JSON.parse(JSON.stringify(value));
    assert.deepEqual(plainData(table.headers.map((header) => header.label)), ['Sign up', 'Sign up (Jalali)', 'SKU', 'Last active', 'Last active (Jalali)']);
    assert.deepEqual(plainData(table.rows.map((row) => row.map((cell) => cell.value))), [
        ['2025-03-20 10:00:00', '1403/12/30', '2025-03-20', '', ''],
        ['2025-03-21T09:00:00', '1404/01/01', 'PK-1', '2024-03-19', '1402/12/29'],
    ]);

    const plain = [[{ value: 'a' }]];
    assert.equal(addJalaliColumns([{ key: 'name', label: 'Name' }], plain).rows, plain);
});
