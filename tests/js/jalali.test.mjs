import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/jalali.js', import.meta.url), 'utf8');
const context = {};
runInNewContext(source, context);
const Jalali = context.PersianKitJalali;

const LEAP_YEARS = [1399, 1403, 1408];

test('leap years from 1399 to 1410', () => {
    for (let year = 1399; year <= 1410; year++) {
        assert.equal(Jalali.isJalaliLeapYear(year), LEAP_YEARS.includes(year), `year ${year}`);
    }
});

test('Esfand has 30 days only in leap years', () => {
    for (let year = 1399; year <= 1410; year++) {
        assert.equal(Jalali.jalaliMonthLength(12, year), LEAP_YEARS.includes(year) ? 30 : 29, `year ${year}`);
    }
});

test('30 Esfand round-trips only in leap years', () => {
    for (let year = 1399; year <= 1410; year++) {
        const [gy, gm, gd] = Jalali.jalaliToGregorian(year, 12, 30);
        const back = Array.from(Jalali.gregorianToJalali(gy, gm, gd));
        const roundTrips = back[0] === year && back[1] === 12 && back[2] === 30;

        assert.equal(roundTrips, LEAP_YEARS.includes(year), `year ${year}`);
    }
});

test('30 Esfand 1404 does not exist and 1 Farvardin 1405 is 2026-03-21', () => {
    assert.equal(Jalali.jalaliMonthLength(12, 1404), 29);
    assert.deepEqual(Array.from(Jalali.jalaliToGregorian(1405, 1, 1)), [2026, 3, 21]);
});

test('other month lengths', () => {
    assert.equal(Jalali.jalaliMonthLength(1, 1404), 31);
    assert.equal(Jalali.jalaliMonthLength(6, 1404), 31);
    assert.equal(Jalali.jalaliMonthLength(7, 1404), 30);
    assert.equal(Jalali.jalaliMonthLength(11, 1404), 30);
});

// Objects from the script's context have that context's prototype; copy them before deepEqual.
const plain = (value) => (value === null ? null : { ...value });

test('parseEditorDate reads the site-local editor string and ignores any offset', () => {
    assert.deepEqual(plain(Jalali.parseEditorDate('2027-01-05T10:30:00')), { gy: 2027, gm: 1, gd: 5, hh: 10, mn: 30, ss: 0 });
    assert.deepEqual(plain(Jalali.parseEditorDate('2027-01-05T10:30')), { gy: 2027, gm: 1, gd: 5, hh: 10, mn: 30, ss: 0 });
    assert.deepEqual(plain(Jalali.parseEditorDate('2027-01-05T23:59:59+03:30')), { gy: 2027, gm: 1, gd: 5, hh: 23, mn: 59, ss: 59 });
});

test('parseEditorDate rejects malformed and impossible dates', () => {
    for (const input of ['', 'not a date', '2027-1-5T10:30', '2027-01-05', '2027-13-01T00:00', '2027-02-29T00:00', '2027-01-05T24:00', null, undefined]) {
        assert.equal(Jalali.parseEditorDate(input), null, String(input));
    }
    assert.notEqual(Jalali.parseEditorDate('2028-02-29T00:00'), null);
});

test('15 Dey 1405 10:30 round-trips with 2027-01-05T10:30:00', () => {
    const parts = { jy: 1405, jm: 10, jd: 15, hh: 10, mn: 30 };

    assert.deepEqual(plain(Jalali.toJalaliParts('2027-01-05T10:30:00')), parts);
    assert.equal(Jalali.fromJalaliParts(parts), '2027-01-05T10:30:00');
    assert.equal(Jalali.toJalaliParts('garbage'), null);
});

test('clampJalaliParts keeps the day inside the month', () => {
    assert.equal(Jalali.clampJalaliParts({ jy: 1405, jm: 12, jd: 30, hh: 0, mn: 0 }).jd, 29);
    assert.equal(Jalali.clampJalaliParts({ jy: 1408, jm: 12, jd: 30, hh: 0, mn: 0 }).jd, 30);
    assert.equal(Jalali.fromJalaliParts({ jy: 1405, jm: 12, jd: 30, hh: 0, mn: 0 }), Jalali.fromJalaliParts({ jy: 1405, jm: 12, jd: 29, hh: 0, mn: 0 }));
});

test('clampJalaliParts limits every part and rejects non-numbers', () => {
    assert.deepEqual(
        plain(Jalali.clampJalaliParts({ jy: 999, jm: 13, jd: 0, hh: 24, mn: -1 })),
        { jy: 1300, jm: 12, jd: 1, hh: 23, mn: 0 }
    );
    assert.deepEqual(
        plain(Jalali.clampJalaliParts({ jy: '1600', jm: '7', jd: '31', hh: '9', mn: '75' })),
        { jy: 1500, jm: 7, jd: 30, hh: 9, mn: 59 }
    );
    assert.equal(Jalali.clampJalaliParts({ jy: 1405, jm: 1, jd: 1, hh: 'x', mn: 0 }), null);
    assert.equal(Jalali.fromJalaliParts({ jy: 1405 }), null);
});

test('formatJalaliLabel', () => {
    assert.equal(Jalali.formatJalaliLabel({ jy: 1405, jm: 10, jd: 15, hh: 10, mn: 30 }), '15 دی 1405 10:30');
    assert.equal(Jalali.formatJalaliLabel({ jy: 1405, jm: 1, jd: 1, hh: 7, mn: 5 }), '1 فروردین 1405 07:05');
});

const DARI = {
    months: ['', 'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'],
    monthsShort: ['', 'حمل', 'ثور', 'جوزا', 'سرطان', 'اسد', 'سنبله', 'میزان', 'عقرب', 'قوس', 'جدی', 'دلو', 'حوت'],
    weekdays: ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه', 'شنبه'],
    weekdaysShort: ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'],
    seasons: ['بهار', 'تابستان', 'خزان', 'زمستان'],
    ordinal: 'ام',
};

test('month names come from the page (JalaliScript.php)', () => {
    const page = { persianKitDateLabels: { names: DARI } };
    runInNewContext(source, page);

    assert.equal(page.PersianKitJalali.JALALI_MONTHS[7], 'میزان');
    assert.equal(page.PersianKitJalali.formatJalaliLabel({ jy: 1405, jm: 7, jd: 12, hh: 9, mn: 5 }), '12 میزان 1405 09:05');
    assert.equal(page.PersianKitJalali.names.seasons[2], 'خزان');
    assert.equal(page.PersianKitJalali.names.ordinal, 'ام');
});

test('without names, or with a list cut short, the Iranian names', () => {
    assert.equal(Jalali.JALALI_MONTHS[7], 'مهر');
    assert.deepEqual({ ...Jalali.names }, {});

    const page = { persianKitDateLabels: { names: { months: ['', 'حمل'], weekdays: DARI.weekdays, seasons: 'خزان' } } };
    runInNewContext(source, page);

    assert.equal(page.PersianKitJalali.JALALI_MONTHS[7], 'مهر');
    assert.equal(page.PersianKitJalali.JALALI_WEEKDAYS[4], 'پنج‌شنبه', 'a complete list is kept');
    assert.equal(page.PersianKitJalali.names.seasons, undefined);
});
