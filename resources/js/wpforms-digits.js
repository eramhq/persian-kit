/**
 * Persian Kit — English digits in WPForms' number fields.
 *
 * WPForms' Number field is a number input, which drops Persian digits,
 * and its browser checks reject a price or Iranian value typed with them.
 * As people type or paste, Persian and Arabic digits become English digits
 * in Number inputs, a price the customer enters, and the Iranian fields.
 * Other text keeps its digits; form-digits.js does the swap.
 */
(function (window) {
    'use strict';

    var SELECTOR = [
        '.wpforms-field-number input',
        '.wpforms-field-payment-single input[type="text"]',
        '.wpforms-field-persian-kit-mobile input',
        '.wpforms-field-persian-kit-national-id input',
        '.wpforms-field-persian-kit-postcode input',
        '.wpforms-field-persian-kit-card input',
        '.wpforms-field-persian-kit-iban input',
    ].map(function (input) {
        return '.wpforms-container ' + input;
    }).join(', ');

    window.PersianKitFormDigits.watch(SELECTOR);
})(window);
