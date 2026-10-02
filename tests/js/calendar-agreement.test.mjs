import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { CalendarDate, GregorianCalendar, PersianCalendar, toCalendar } from '@internationalized/date';

// The date picker's Persian calendar (@internationalized/date, ICU's rule),
// the admin date editors (resources/js/jalali.js) and the server (daynum,
// tests/Unit/DateConversion/CalendarAgreementTest.php) must agree, or a
// picked date is saved one day off. tests/fixtures/persian-years.json holds,
// for each year, the Gregorian date of 1 Farvardin and the length of Esfand.
const years = JSON.parse(readFileSync(new URL('../fixtures/persian-years.json', import.meta.url), 'utf8'));

const context = {};
runInNewContext(readFileSync(new URL('../../resources/js/jalali.js', import.meta.url), 'utf8'), context);
const Jalali = context.PersianKitJalali;

const persian = new PersianCalendar();
const gregorian = new GregorianCalendar();

test('the fixture covers 1300 to 1500 AP', () => {
    assert.deepEqual(Object.keys(years).map(Number), Array.from({ length: 201 }, (_, i) => 1300 + i));
});

test('the fixture is what the date picker calendar computes', () => {
    for (const [year, [farvardin1, esfandLength]] of Object.entries(years)) {
        const date = new CalendarDate(persian, Number(year), 1, 1);

        assert.equal(toCalendar(date, gregorian).toString(), farvardin1, `1 Farvardin ${year}`);
        assert.equal(persian.getDaysInMonth(new CalendarDate(persian, Number(year), 12, 1)), esfandLength, `Esfand ${year}`);
    }
});

test('the admin date editors agree with the date picker', () => {
    for (const [year, [farvardin1, esfandLength]] of Object.entries(years)) {
        const [gy, gm, gd] = Jalali.jalaliToGregorian(Number(year), 1, 1);
        const iso = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;

        assert.equal(iso, farvardin1, `1 Farvardin ${year}`);
        assert.equal(Jalali.jalaliMonthLength(12, Number(year)), esfandLength, `Esfand ${year}`);
    }
});
