// The <intl-datepicker> Web Component with only the Persian calendar and the
// Persian labels. resources/js/date-field.js connects it to form fields.
import 'intl-datepicker';
import 'intl-datepicker/calendars/persian';
import { LABELS_FA } from 'intl-datepicker/labels/fa';
import { CalendarDate, GregorianCalendar, PersianCalendar, toCalendar } from '@internationalized/date';

// Jalali and ISO dates converted with the picker's own calendar, for the
// scripts that read or write a date outside a picker.
const persian = new PersianCalendar();
const gregorian = new GregorianCalendar();

// Languages the picker has no labels for, whose readers read Persian.
const PERSIAN_LABELS = ['ps', 'ckb'];

// For a browser without a language's Jalali names (Chrome has no Pashto
// data, and only the Sorani months of ckb-IR), the nearest locale it has.
const FALLBACK_LOCALES = { ps: 'fa-AF', ckb: 'fa-IR' };

function language(locale) {
    return String(locale || '').toLowerCase().split('-')[0];
}

/** Whether the browser writes Jalali months and weekdays of the locale in Arabic script. */
function hasJalaliNames(locale) {
    try {
        const format = new Intl.DateTimeFormat(locale, { calendar: 'persian', month: 'long', weekday: 'long', timeZone: 'UTC' });

        return format.resolvedOptions().calendar === 'persian' && !/[A-Za-z]/.test(format.format(new Date(Date.UTC(2026, 9, 4))));
    } catch (error) {
        return false;
    }
}

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

    /**
     * The locale for a picker: the one asked for, or for Pashto and Kurdish,
     * fa-AF or fa-IR when the browser lacks their Jalali names. A locale it
     * does not know would fall back to English and the Gregorian calendar.
     */
    pickerLocale(locale) {
        const fallback = FALLBACK_LOCALES[language(locale)];

        return fallback && !hasJalaliNames(locale) ? fallback : locale;
    },

    /**
     * The Persian labels as JSON, for the picker's labels attribute, when
     * the locale is Pashto or Kurdish; otherwise null, and the picker uses
     * its own (Persian for fa, English for most others).
     */
    labelsFor(locale) {
        return PERSIAN_LABELS.includes(language(locale)) ? JSON.stringify(LABELS_FA) : null;
    },
};
