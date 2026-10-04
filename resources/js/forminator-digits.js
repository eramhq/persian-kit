/**
 * Persian Kit — English digits in Forminator's phone and number fields.
 *
 * Forminator checks phone numbers and numbers in the browser before the
 * form is sent, so a mobile typed as ۰۹۱۲… would be rejected there. As
 * people type or paste, Persian and Arabic digits become English digits in
 * Phone, Number and Currency inputs and in fields with a persian-kit-*
 * class (Iranian checks). Other text keeps its digits.
 *
 * Number and Currency fields with a thousands separator use Inputmask,
 * which drops a digit it doesn't know before the input changes;
 * form-digits.js swaps typed digits before it sees them.
 */
(function (window) {
    'use strict';

    var SELECTOR = [
        'input.forminator-field--phone',
        'input.forminator-number--field',
        'input.forminator-currency',
        '.persian-kit-mobile input',
        '.persian-kit-national-id input',
        '.persian-kit-postcode input',
        '.persian-kit-card input',
        '.persian-kit-iban input',
    ].map(function (input) {
        return '.forminator-custom-form ' + input;
    }).join(', ');

    var digits = window.PersianKitFormDigits;
    digits.watch(SELECTOR);

    window.PersianKitForminatorDigits = {
        selector: SELECTOR,
        toAsciiDigits: digits.toAsciiDigits,
        fix: digits.fix,
    };
})(window);
