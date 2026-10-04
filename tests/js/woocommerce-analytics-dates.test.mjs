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
