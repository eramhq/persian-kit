/**
 * Persian Kit — Jalali (Shamsi) calendar conversion library.
 *
 * Algorithm ported from Ali Farhadi's jdate.js (GPL-2.0).
 * Provides Gregorian↔Jalali conversion, leap-year check, and month lengths.
 *
 * Exposed as window.PersianKitJalali for use by other scripts.
 */
(function (root) {
    'use strict';

    var JALALI_MONTHS = [
        '',
        'فروردین',
        'اردیبهشت',
        'خرداد',
        'تیر',
        'مرداد',
        'شهریور',
        'مهر',
        'آبان',
        'آذر',
        'دی',
        'بهمن',
        'اسفند'
    ];

    var JALALI_WEEKDAYS = [
        'یکشنبه',
        'دوشنبه',
        'سه‌شنبه',
        'چهارشنبه',
        'پنجشنبه',
        'جمعه',
        'شنبه'
    ];

    /**
     * Convert Gregorian date to Jalali.
     *
     * @param {number} gy Gregorian year
     * @param {number} gm Gregorian month (1-12)
     * @param {number} gd Gregorian day (1-31)
     * @returns {[number, number, number]} [jy, jm, jd]
     */
    function gregorianToJalali(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = 355666 +
            (365 * gy) +
            Math.floor((gy2 + 3) / 4) -
            Math.floor((gy2 + 99) / 100) +
            Math.floor((gy2 + 399) / 400) +
            gd +
            g_d_m[gm - 1];

        var jy = -1595 + (33 * Math.floor(days / 12053));
        days %= 12053;

        jy += 4 * Math.floor(days / 1461);
        days %= 1461;

        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }

        var jm, jd;
        if (days < 186) {
            jm = 1 + Math.floor(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + Math.floor((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }

        return [jy, jm, jd];
    }

    /**
     * Convert Jalali date to Gregorian.
     *
     * @param {number} jy Jalali year
     * @param {number} jm Jalali month (1-12)
     * @param {number} jd Jalali day (1-31)
     * @returns {[number, number, number]} [gy, gm, gd]
     */
    function jalaliToGregorian(jy, jm, jd) {
        jy += 1595;
        var days = -355668 +
            (365 * jy) +
            (Math.floor(jy / 33) * 8) +
            Math.floor(((jy % 33) + 3) / 4) +
            jd +
            ((jm < 7) ? ((jm - 1) * 31) : (((jm - 7) * 30) + 186));

        var gy = 400 * Math.floor(days / 146097);
        days %= 146097;

        if (days > 36524) {
            gy += 100 * Math.floor(--days / 36524);
            days %= 36524;
            if (days >= 365) {
                days++;
            }
        }

        gy += 4 * Math.floor(days / 1461);
        days %= 1461;

        if (days > 365) {
            gy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }

        var gd = days;
        var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28,
            31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm;
        for (gm = 0; gm < 13 && gd >= sal_a[gm]; gm++) {
            gd -= sal_a[gm];
        }

        return [gy, gm, gd + 1];
    }

    /**
     * Check if a Jalali year is a leap year (33-year cycle).
     *
     * @param {number} jy Jalali year
     * @returns {boolean}
     */
    function isJalaliLeapYear(jy) {
        var breaks = [1, 5, 9, 13, 17, 22, 26, 30];
        var mod = ((jy - 474) % 2820 + 2820) % 2820 + 474;
        var remainder = (mod + 38) * 682 % 2816;
        return remainder < 682;
    }

    /**
     * Get the number of days in a Jalali month.
     *
     * Months 1-6: 31 days, months 7-11: 30 days, month 12: 29 (or 30 in leap year).
     *
     * @param {number} jm Jalali month (1-12)
     * @param {number} jy Jalali year
     * @returns {number}
     */
    function jalaliMonthLength(jm, jy) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;
        return isJalaliLeapYear(jy) ? 30 : 29;
    }

    /**
     * Zero-pad a number to 2 digits.
     *
     * @param {number} n
     * @returns {string}
     */
    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    var api = {
        gregorianToJalali: gregorianToJalali,
        jalaliToGregorian: jalaliToGregorian,
        isJalaliLeapYear: isJalaliLeapYear,
        jalaliMonthLength: jalaliMonthLength,
        JALALI_MONTHS: JALALI_MONTHS,
        JALALI_WEEKDAYS: JALALI_WEEKDAYS,
        pad: pad
    };

    root.PersianKitJalali = api;

})(typeof window !== 'undefined' ? window : this);
