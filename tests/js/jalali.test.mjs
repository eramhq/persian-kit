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
