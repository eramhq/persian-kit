/**
 * Persian Kit — English digits in Gravity Forms' number-like fields.
 *
 * Gravity Forms masks phone numbers, prices and Date Picker inputs (IMask
 * since 3.0, maskedinput before), and the masks drop Persian digits. As
 * people type or paste, Persian and Arabic digits become English digits in
 * Phone, Number, Time and Date inputs, the address's postcode, product
 * quantities and prices, and the Iranian fields. Other text keeps its
 * digits; form-digits.js does the swap.
 */
(function (window) {
    'use strict';

    var SELECTOR = [
        '.ginput_container_phone input',
        '.ginput_container_number input',
        '.ginput_container_time input',
        '.ginput_container_date input',
        '.address_zip input',
        'input.ginput_quantity',
        'input.ginput_amount',
        '.ginput_container_persian_kit input',
    ].map(function (input) {
        return '.gform_wrapper ' + input;
    }).join(', ');

    window.PersianKitFormDigits.watch(SELECTOR);
})(window);
