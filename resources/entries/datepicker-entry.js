// The <intl-datepicker> Web Component with only the Persian calendar and the
// Persian labels. resources/js/date-field.js connects it to form fields.
import 'intl-datepicker';
import 'intl-datepicker/calendars/persian';
import 'intl-datepicker/labels/fa';
import { CalendarDate, GregorianCalendar, PersianCalendar, toCalendar } from '@internationalized/date';

// Jalali and ISO dates converted with the picker's own calendar, for the
// scripts that read or write a date outside a picker.
const persian = new PersianCalendar();
const gregorian = new GregorianCalendar();

window.PersianKitCalendar = {
    /** A Jalali date as an ISO date, or null when there is no such day. */
    jalaliToIso(year, month, day) {
        year = Number(year);
        month = Number(month);
        day = Number(day);

        if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day) || month < 1 || month > 12 || day < 1) {
            return null;
        }

        const date = new CalendarDate(persian, year, month, 1);
        if (day > persian.getDaysInMonth(date)) {
            return null;
        }

        return toCalendar(date.set({ day }), gregorian).toString();
    },

    /** An ISO date as {year, month, day} in the Jalali calendar, or null. */
    isoToJalali(iso) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''));
        if (!match) {
            return null;
        }

        const date = new CalendarDate(gregorian, Number(match[1]), Number(match[2]), Number(match[3]));
        if (date.toString() !== match[0]) {
            return null;
        }

        const jalali = toCalendar(date, persian);

        return { year: jalali.year, month: jalali.month, day: jalali.day };
    },
};
