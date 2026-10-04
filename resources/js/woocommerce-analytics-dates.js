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
 * wp.date is wrapped the same way: dates printed for people (tables, chart
 * axes and tooltips) come out Jalali, while formats machines read, such as
 * Ymd in the link to the orders list, stay Gregorian.
 *
 * Stats requests by month, season or year carry a flag, and the server
 * groups them by Jalali period (WooAnalyticsIntervals.php).
 *
 * Each part is installed by a script printed right after the WooCommerce or
 * WordPress script it replaces (WooAnalyticsDates.php), before anything
 * reads it. If wc.date is missing or has changed shape, nothing is replaced
 * and Analytics stays Gregorian.
 *
 * Depends on: PersianKitJalali; moment with moment-timezone (wp-date) when
 * wc.date is installed.
 */
(function (window) {
    'use strict';

    var Jalali = window.PersianKitJalali;
    var config = window.persianKitAnalyticsDates || {};
    var labels = config.labels || {};

    /** Query parameter that asks the server for Jalali periods (WooAnalyticsIntervals::FLAG). */
    var FLAG = 'persian_kit_calendar';

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
    var MONTHS_SHORT = ['', 'فرو', 'ارد', 'خرد', 'تیر', 'مرد', 'شهر', 'مهر', 'آبا', 'آذر', 'دی', 'بهم', 'اسف'];
    var WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'];
    var WEEKDAYS_SHORT = ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'];
    var SEASONS = ['بهار', 'تابستان', 'پاییز', 'زمستان'];

    /**
     * Formats machines read, as in DateDisplayGuard::isMachineFormat(), and
     * WooCommerce's own: Ymd in links, Y-m-d\TH:i:s for chart keys.
     */
    var MACHINE_FORMATS = [
        'U',
        'G',
        'c',
        'r',
        'Y-m-d\\TH:i:s\\Z',
        'Y-m-d\\TH:i:sP',
        'Y-m-d\\TH:i:sO',
        'Y-m-d\\TH:i:s.vP',
        'X-m-d\\TH:i:sP',
        'l, d-M-Y H:i:s T',
        'l, d-M-y H:i:s T',
        'D, d M y H:i:s O',
        'D, d M Y H:i:s O',
        'D, d M Y H:i:s \\G\\M\\T',
    ];
    var ISO_FORMAT = /^Y(-m-d|md)((\\T| )H:i(:s)?)?$/;

    /** Tokens that differ between the calendars; 'Q' is the season, ours only. */
    var CALENDAR_TOKENS = /[dDjlNSwzWFmMntLoYyQ]/;


    var startOfWeek = Math.min(6, Math.max(0, parseInt(config.startOfWeek, 10) || 0));

    var state = {
        /** The original wc.date, once replaced; the other parts wait for it. */
        original: null,
        apiFetch: false,
    };

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function toAsciiDigits(value) {
        return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
            var code = digit.charCodeAt(0);
            return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
        });
    }

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

    /** Formats for the chart and tables, read by the wrapped wp.date. */
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
                formats.screenReaderFormat = formats.tooltipLabelFormat = 'Q Y';
                formats.xFormat = 'Q';
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

    // --- wp.date: Jalali display formats ------------------------------------

    function isMachineFormat(format) {
        return MACHINE_FORMATS.indexOf(format) !== -1 || ISO_FORMAT.test(format);
    }

    function hasCalendarToken(format) {
        return CALENDAR_TOKENS.test(format.replace(/\\[\s\S]/g, ''));
    }

    /**
     * format() with the calendar tokens in Jalali, as JalaliFormatter does on
     * the server. The Gregorian day comes from the original function, so its
     * time zone handling stays; the other tokens are its own output.
     */
    function formatJalali(original, args) {
        var format = args[0];
        var rest = Array.prototype.slice.call(args, 1);
        var call = function (token) {
            return original.apply(null, [token].concat(rest));
        };
        var ymd = /^(\d{4})-(\d{2})-(\d{2})$/.exec(toAsciiDigits(call('Y-m-d')));

        if (!ymd) {
            return original.apply(null, args);
        }

        var gy = +ymd[1];
        var gm = +ymd[2];
        var gd = +ymd[3];
        var j = Jalali.gregorianToJalali(gy, gm, gd);
        var weekday = new Date(Date.UTC(gy, gm - 1, gd)).getUTCDay();
        var jalaliWeekday = (weekday + 1) % 7 + 1;
        var dayOfYear = (j[1] <= 6 ? (j[1] - 1) * 31 : 186 + (j[1] - 7) * 30) + j[2];
        var out = '';

        for (var i = 0; i < format.length; i++) {
            var token = format.charAt(i);

            if (token === '\\') {
                i++;
                out += format.charAt(i);
                continue;
            }

            switch (token) {
                case 'd': out += pad(j[2]); break;
                case 'D': out += WEEKDAYS_SHORT[weekday]; break;
                case 'j': out += j[2]; break;
                case 'l': out += WEEKDAYS[weekday]; break;
                case 'N': out += jalaliWeekday; break;
                case 'S': out += 'ام'; break;
                case 'w': out += jalaliWeekday - 1; break;
                case 'z': out += dayOfYear; break;
                case 'W': out += Math.floor(dayOfYear / 7) + 1; break;
                case 'F': out += MONTHS[j[1]]; break;
                case 'm': out += pad(j[1]); break;
                case 'M': out += MONTHS_SHORT[j[1]]; break;
                case 'n': out += j[1]; break;
                case 't': out += Jalali.jalaliMonthLength(j[1], j[0]); break;
                case 'L': out += Jalali.isJalaliLeapYear(j[0]) ? '1' : '0'; break;
                case 'o':
                case 'Y': out += j[0]; break;
                case 'y': out += pad(j[0] % 100); break;
                case 'Q': out += SEASONS[Math.floor((j[1] - 1) / 3)]; break;
                default: out += /[a-zA-Z]/.test(token) ? call(token) : token;
            }
        }

        return out;
    }

    function wrapFormatter(original) {
        return function (format) {
            if (!isInstalled() || typeof format !== 'string' || isMachineFormat(format) || !hasCalendarToken(format)) {
                return original.apply(this, arguments);
            }

            return formatJalali(original, arguments);
        };
    }

    /** Wraps wp.date's formatters; they stay Gregorian until wc.date is replaced. */
    function installWpDate() {
        var wp = window.wp;
        var original = wp && wp.date;

        if (!original || typeof original.format !== 'function' || original.persianKitJalali) {
            return false;
        }

        var members = { persianKitJalali: true };
        ['format', 'date', 'gmdate', 'dateI18n', 'gmdateI18n'].forEach(function (name) {
            if (typeof original[name] === 'function') {
                members[name] = wrapFormatter(original[name]);
            }
        });
        wp.date = copyModule(original, members);

        return true;
    }

    // --- Stats requests: the flag for Jalali periods ------------------------

    function isJalaliStatsRequest(url) {
        var decoded = url;
        try {
            decoded = decodeURIComponent(url);
        } catch (error) {
            // Keep the raw value.
        }

        return /\/wc-analytics\/[^?&#]+\/stats(?=[?&#]|$)/.test(decoded)
            && /[?&]interval=(month|quarter|year)(?=[&#]|$)/.test(decoded);
    }

    function withFlag(options) {
        var key = typeof options.path === 'string' ? 'path' : (typeof options.url === 'string' ? 'url' : '');

        if (!key || !isJalaliStatsRequest(options[key])) {
            return options;
        }

        var copy = Object.assign({}, options);
        copy[key] = options[key] + (options[key].indexOf('?') === -1 ? '?' : '&') + FLAG + '=jalali';

        return copy;
    }

    function installApiFetch() {
        var apiFetch = window.wp && window.wp.apiFetch;

        if (state.apiFetch || !apiFetch || typeof apiFetch.use !== 'function') {
            return false;
        }

        apiFetch.use(function (options, next) {
            return next(isInstalled() ? withFlag(options) : options);
        });
        state.apiFetch = true;

        return true;
    }

    window.PersianKitAnalyticsDates = {
        installWpDate: installWpDate,
        installWcDate: installWcDate,
        installApiFetch: installApiFetch,
        // For tests.
        isMachineFormat: isMachineFormat,
        withFlag: withFlag,
    };
})(window);
