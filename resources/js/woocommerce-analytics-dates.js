/**
 * Persian Kit — Jalali dates in WooCommerce Analytics.
 *
 * Every Analytics screen asks one shared WooCommerce helper, window.wc.date,
 * which dates make up a period ("last month") and how to format an interval.
 * Before the screens load, that object is replaced with one that answers in
 * the Jalali calendar: months, seasons (WooCommerce's quarters) and years
 * begin on Jalali dates, ranges read "1 - 31 شهریور 1404", and "previous
 * year" goes back one Jalali year. URLs keep WooCommerce's keys
 * (period=last_month), so a bookmark shows the Jalali period of the day it
 * is opened.
 *
 * It is installed by a script printed right after WooCommerce's
 * (WooAnalyticsDates.php), before anything reads it. If wc.date is missing
 * or has changed shape, nothing is replaced and Analytics stays Gregorian.
 *
 * Depends on: PersianKitJalali; moment with moment-timezone (wp-date) when
 * wc.date is installed.
 */
(function (window) {
    'use strict';

    var Jalali = window.PersianKitJalali;
    var config = window.persianKitAnalyticsDates || {};
    var labels = config.labels || {};

    /** Months in a WooCommerce interval; a quarter is a Jalali season. */
    var MONTHS_IN = { month: 1, quarter: 3, year: 12 };

    /** wc.date members read here; if one is missing, nothing is replaced. */
    var CONTRACT = [
        'getCurrentDates',
        'getDateParamsFromQuery',
        'getStoreTimeZoneMoment',
        'getPreviousDate',
        'getDateFormatsForInterval',
        'getDateFormatsForIntervalPhp',
        'containsLeapYear',
    ];

    var MONTHS = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    var startOfWeek = Math.min(6, Math.max(0, parseInt(config.startOfWeek, 10) || 0));

    var state = {
        /** The original wc.date, once replaced. */
        original: null,
    };

    function isInstalled() {
        return state.original !== null;
    }

    // --- Jalali periods, as moments in their own time zone -----------------

    function toJalali(m) {
        return Jalali.gregorianToJalali(m.year(), m.month() + 1, m.date());
    }

    /** 00:00 on a Jalali day, in base's time zone. */
    function atJalali(base, jy, jm, jd) {
        var g = Jalali.jalaliToGregorian(jy, jm, jd);

        return base.clone().set({ year: g[0], month: g[1] - 1, date: g[2] }).startOf('day');
    }

    /** The same Jalali day and time n months later, or the month's last day if it is shorter. */
    function addJalaliMonths(m, n) {
        var j = toJalali(m);
        var index = j[0] * 12 + j[1] - 1 + n;
        var jy = Math.floor(index / 12);
        var jm = index - jy * 12 + 1;
        var jd = Math.min(j[2], Jalali.jalaliMonthLength(jm, jy));

        return atJalali(m, jy, jm, jd).set({
            hour: m.hour(),
            minute: m.minute(),
            second: m.second(),
            millisecond: m.millisecond(),
        });
    }

    /** 00:00 on the first day of the day, week, Jalali month, season or year holding m. */
    function startOfPeriod(m, unit) {
        var day = m.clone().startOf('day');

        if (unit === 'week') {
            return day.subtract((day.day() - startOfWeek + 7) % 7, 'days');
        }
        if (!MONTHS_IN[unit]) {
            return day;
        }

        var j = toJalali(m);
        var jm = unit === 'year' ? 1 : (unit === 'quarter' ? j[1] - (j[1] - 1) % 3 : j[1]);

        return atJalali(m, j[0], jm, 1);
    }

    function addPeriods(m, unit, n) {
        if (MONTHS_IN[unit]) {
            return addJalaliMonths(m, n * MONTHS_IN[unit]);
        }

        return m.clone().add(unit === 'week' ? 7 * n : n, 'days');
    }

    /** The last moment of the period that begins at start. */
    function endOfPeriod(start, unit) {
        return addPeriods(start, unit, 1).subtract(1, 'days').endOf('day');
    }

    function storeNow() {
        return state.original.getStoreTimeZoneMoment();
    }

    /** "Month to date" and the like: from the period's first day to now. */
    function getCurrentPeriod(unit, compare) {
        var now = storeNow();
        var primaryStart = startOfPeriod(now, unit);
        var secondaryStart;
        var secondaryEnd;

        if (compare === 'previous_period') {
            secondaryStart = addPeriods(primaryStart, unit, -1);
            secondaryEnd = addPeriods(now, unit, -1);
        } else {
            // As WooCommerce does: as many days from the same start a year back.
            secondaryStart = addJalaliMonths(primaryStart, -12);
            secondaryEnd = secondaryStart.clone().add(now.diff(primaryStart, 'days') + 1, 'days').subtract(1, 'seconds');
        }

        return { primaryStart: primaryStart, primaryEnd: now, secondaryStart: secondaryStart, secondaryEnd: secondaryEnd };
    }

    /** "Last month" and the like: the whole period before the current one. */
    function getLastPeriod(unit, compare) {
        var primaryStart = addPeriods(startOfPeriod(storeNow(), unit), unit, -1);
        var primaryEnd = endOfPeriod(primaryStart, unit);
        var secondaryStart;
        var secondaryEnd;

        if (compare === 'previous_period') {
            // The whole Jalali month, season or year before, as a tax period.
            secondaryStart = addPeriods(primaryStart, unit, -1);
            secondaryEnd = endOfPeriod(secondaryStart, unit);
        } else {
            secondaryStart = addJalaliMonths(primaryStart, -12);
            secondaryEnd = MONTHS_IN[unit] ? endOfPeriod(secondaryStart, unit) : addJalaliMonths(primaryEnd, -12);
        }

        return { primaryStart: primaryStart, primaryEnd: primaryEnd, secondaryStart: secondaryStart, secondaryEnd: secondaryEnd };
    }

    function getCustomPeriod(after, before, compare) {
        if (!after || !before) {
            throw new Error('Custom date range requires both after and before dates.');
        }

        if (compare === 'previous_period') {
            // As WooCommerce does: the same number of days, ending the day before.
            var end = after.clone().subtract(1, 'days');

            return { primaryStart: after, primaryEnd: before, secondaryStart: end.clone().subtract(before.diff(after, 'days'), 'days'), secondaryEnd: end };
        }

        // 30 Esfand of a leap year becomes 29 Esfand.
        return { primaryStart: after, primaryEnd: before, secondaryStart: addJalaliMonths(after, -12), secondaryEnd: addJalaliMonths(before, -12) };
    }

    function getPeriodDates(period, compare, after, before) {
        switch (period) {
            case 'today':
                return getCurrentPeriod('day', compare);
            case 'yesterday':
                return getLastPeriod('day', compare);
            case 'week':
                return getCurrentPeriod('week', compare);
            case 'last_week':
                return getLastPeriod('week', compare);
            case 'month':
                return getCurrentPeriod('month', compare);
            case 'last_month':
                return getLastPeriod('month', compare);
            case 'quarter':
                return getCurrentPeriod('quarter', compare);
            case 'last_quarter':
                return getLastPeriod('quarter', compare);
            case 'year':
                return getCurrentPeriod('year', compare);
            case 'last_year':
                return getLastPeriod('year', compare);
            case 'custom':
                return getCustomPeriod(after, before, compare);
        }

        throw new Error('Invalid date range');
    }

    /** "9 مهر 1404", "1 - 9 مهر 1404", "1 شهریور - 9 مهر 1404" or with both years. */
    function getRangeLabel(after, before) {
        var a = toJalali(after);
        var b = toJalali(before);

        if (a[0] === b[0] && a[1] === b[1]) {
            return (a[2] === b[2] ? a[2] : a[2] + ' - ' + b[2]) + ' ' + MONTHS[a[1]] + ' ' + a[0];
        }

        var start = a[2] + ' ' + MONTHS[a[1]] + (a[0] === b[0] ? '' : ' ' + a[0]);

        return start + ' - ' + b[2] + ' ' + MONTHS[b[1]] + ' ' + b[0];
    }

    function findOption(options, value) {
        for (var i = 0; i < options.length; i++) {
            if (options[i].value === value) {
                return options[i];
            }
        }

        return null;
    }

    /**
     * Kept by query, as WooCommerce does: the screens compare these objects,
     * and "today" stays the same day while the page is open.
     */
    var currentDates = {};

    function getCurrentDates(query, defaultDateRange) {
        var params = state.original.getDateParamsFromQuery(query, defaultDateRange);
        var key = [
            params.period,
            params.compare,
            params.after ? params.after.format() : '',
            params.before ? params.before.format() : '',
        ].join(':');

        if (!currentDates[key]) {
            var preset = findOption(state.original.presetValues, params.period);
            if (!preset) {
                throw new Error('Cannot find period: ' + params.period);
            }
            var compare = findOption(state.original.periods, params.compare);
            if (!compare) {
                throw new Error('Cannot find compare: ' + params.compare);
            }

            var dates = getPeriodDates(params.period, params.compare, params.after, params.before);
            currentDates[key] = {
                primary: {
                    label: preset.label,
                    range: getRangeLabel(dates.primaryStart, dates.primaryEnd),
                    after: dates.primaryStart,
                    before: dates.primaryEnd,
                },
                secondary: {
                    label: compare.label,
                    range: getRangeLabel(dates.secondaryStart, dates.secondaryEnd),
                    after: dates.secondaryStart,
                    before: dates.secondaryEnd,
                },
            };
        }

        return currentDates[key];
    }

    /** The date in the compared range that matches date, for the chart's tooltip. */
    function getPreviousDate(date, date1, date2, compare, interval) {
        var m = window.moment(date);

        if ((compare || 'previous_year') === 'previous_year') {
            return addJalaliMonths(m, -12);
        }

        if (MONTHS_IN[interval]) {
            var a = toJalali(window.moment(date1));
            var b = toJalali(window.moment(date2));

            return addJalaliMonths(m, -((a[0] - b[0]) * 12 + a[1] - b[1]));
        }

        return state.original.getPreviousDate(date, date1, date2, compare, interval);
    }

    /** A label, escaped for a PHP date format. */
    function escapeFormat(text) {
        return String(text).replace(/([a-zA-Z\\])/g, '\\$1');
    }

    /** Formats for the chart and tables, day before month. */
    function getDateFormatsForIntervalPhp(interval, ticks) {
        var original = state.original;
        var dayTicks = original.dayTicksThreshold || 63;
        var weekTicks = original.weekTicksThreshold || 9;
        var weekOf = labels.weekOf ? escapeFormat(labels.weekOf) + ' ' : '';
        var formats = {
            screenReaderFormat: 'j F Y',
            tooltipLabelFormat: 'j F Y',
            xFormat: 'Y/m/d',
            x2Format: 'F Y',
            tableFormat: original.defaultTableDateFormat || 'm/d/Y',
        };

        ticks = ticks || 0;

        switch (interval) {
            case 'hour':
                formats = { screenReaderFormat: 'gA j F Y', tooltipLabelFormat: 'gA j F Y', xFormat: 'gA', x2Format: 'j F Y', tableFormat: 'h A' };
                break;
            case 'day':
                if (ticks < dayTicks) {
                    formats.xFormat = 'j';
                } else {
                    formats.xFormat = 'F';
                    formats.x2Format = 'Y';
                }
                break;
            case 'week':
                formats.screenReaderFormat = formats.tooltipLabelFormat = weekOf + 'j F Y';
                if (ticks < weekTicks) {
                    formats.xFormat = 'j';
                } else {
                    formats.xFormat = 'F';
                    formats.x2Format = 'Y';
                }
                break;
            case 'month':
                formats.screenReaderFormat = formats.tooltipLabelFormat = 'F Y';
                formats.xFormat = 'F';
                formats.x2Format = 'Y';
                break;
            case 'quarter':
                formats.screenReaderFormat = formats.tooltipLabelFormat = 'F Y';
                formats.xFormat = 'F';
                formats.x2Format = 'Y';
                break;
            case 'year':
                formats.screenReaderFormat = formats.tooltipLabelFormat = formats.xFormat = 'Y';
                break;
        }

        return formats;
    }

    function getDateFormatsForInterval(interval, ticks, options) {
        if (options && options.type === 'php') {
            return getDateFormatsForIntervalPhp(interval, ticks);
        }

        return state.original.getDateFormatsForInterval(interval, ticks, options);
    }

    function fulfilsContract(date) {
        for (var i = 0; i < CONTRACT.length; i++) {
            if (typeof date[CONTRACT[i]] !== 'function') {
                return false;
            }
        }

        return Array.isArray(date.presetValues) && Array.isArray(date.periods);
    }

    /** Copies a webpack module object: its members are getters, so it can't be changed in place. */
    function copyModule(module, members) {
        var copy = Object.assign({}, module, members);
        Object.defineProperty(copy, '__esModule', { value: true });

        return copy;
    }

    /** Puts the Jalali wc.date in place; false when WooCommerce's has changed shape. */
    function installWcDate() {
        var wc = window.wc;
        var original = wc && wc.date;

        if (isInstalled() || !original || !Jalali || !window.moment || !fulfilsContract(original)) {
            return false;
        }

        // getDateParamsFromQuery, getAllowedIntervalsForQuery and
        // getIntervalForQuery give the same answers in both calendars.
        wc.date = copyModule(original, {
            getCurrentDates: getCurrentDates,
            getCurrentPeriod: getCurrentPeriod,
            getLastPeriod: getLastPeriod,
            getRangeLabel: getRangeLabel,
            getPreviousDate: getPreviousDate,
            getDateFormatsForInterval: getDateFormatsForInterval,
            getDateFormatsForIntervalPhp: getDateFormatsForIntervalPhp,
            // The chart moves the compared days around 29 February; Jalali
            // years line up by Jalali day, so that would misplace them.
            containsLeapYear: function () {
                return false;
            },
        });
        state.original = original;

        return true;
    }

    window.PersianKitAnalyticsDates = {
        installWcDate: installWcDate,
    };
})(window);
