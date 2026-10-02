/**
 * Persian Kit — Jalali date pickers for the WooCommerce admin date fields:
 * sale schedules (products and variations), coupon expiry, the order date
 * and download access expiry.
 *
 * Each field is marked for the date field script (date-field.js), which
 * hides it and shows a picker that writes the Gregorian date back, so
 * WooCommerce reads the field as before. jQuery UI's picker stays attached
 * to the hidden field and never opens.
 *
 * Depends on: jQuery (for WooCommerce's events), PersianKitDateField.
 */
(function ($, window, document) {
    'use strict';

    var field = window.PersianKitDateField;

    if (!$ || !field) {
        return;
    }

    var DATE_FIELDS = [
        '#_sale_price_dates_from',
        '#_sale_price_dates_to',
        '#expiry_date',
        'input[name="order_date"]',
        'input[name^="variable_sale_price_dates_from["]',
        'input[name^="variable_sale_price_dates_to["]',
        'input[name^="access_expires["]',
    ].join(', ');

    var SALE_FROM = '#_sale_price_dates_from, input[name^="variable_sale_price_dates_from["]';
    var SALE_TO = '#_sale_price_dates_to, input[name^="variable_sale_price_dates_to["]';

    var TIME_FIELDS = 'input[name="order_date_hour"], input[name="order_date_minute"], input[name="order_date_second"]';

    /** Time fields whose first value was read. */
    var timeFields = new WeakSet();

    function toAsciiDigits(value) {
        return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
            var code = digit.charCodeAt(0);
            return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
        });
    }

    function each(root, selector, callback) {
        if (root.matches && root.matches(selector)) {
            callback(root);
        }
        if (root.querySelectorAll) {
            Array.prototype.forEach.call(root.querySelectorAll(selector), callback);
        }
    }

    /** Lets one end of a sale schedule limit the other, as jQuery UI did. */
    function limitSaleDates(group) {
        var from = group.querySelector(SALE_FROM);
        var to = group.querySelector(SALE_TO);
        var fromPicker = from && field.picker(from);
        var toPicker = to && field.picker(to);

        if (!fromPicker || !toPicker) {
            return;
        }

        setLimit(toPicker, 'min', fromPicker.value);
        setLimit(fromPicker, 'max', toPicker.value);
    }

    function setLimit(picker, name, value) {
        if (value) {
            picker.setAttribute(name, value);
        } else {
            picker.removeAttribute(name);
        }
    }

    function prepare(input) {
        if (input.hasAttribute('data-persian-kit-date')) {
            return;
        }

        // "From… YYYY-MM-DD": the format is wrong for a Jalali picker.
        var placeholder = input.getAttribute('placeholder');
        if (placeholder !== null) {
            placeholder = placeholder.replace(/\s*YYYY-MM-DD\s*/, ' ').trim();
            if (placeholder) {
                input.setAttribute('placeholder', placeholder);
            } else {
                input.removeAttribute('placeholder');
            }
        }

        input.setAttribute('data-persian-kit-date', '');
        input.setAttribute('data-persian-kit-date-hint', 'off');
    }

    function upgrade(root) {
        each(root, DATE_FIELDS, prepare);
        field.upgradeAll(root);

        // date_i18n() may have written a Jalali date (Date Conversion's
        // global option); the picker read it, and WooCommerce saves the
        // Gregorian date even if nobody changes the field.
        each(root, DATE_FIELDS, function (input) {
            var picker = field.picker(input);
            if (picker && picker.value && input.value !== picker.value) {
                input.value = picker.value;
            }
        });

        each(root, '.sale_price_dates_fields', limitSaleDates);

        each(root, TIME_FIELDS, function (input) {
            if (timeFields.has(input)) {
                return;
            }
            timeFields.add(input);

            // A number input refuses Persian digits as they are typed, and
            // drops a value written with them. As text, the digits arrive
            // and are converted; WooCommerce's pattern still checks them.
            var value = toAsciiDigits(input.value || input.getAttribute('value') || '');
            if (input.type === 'number') {
                input.type = 'text';
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('maxlength', '2');
            }
            if (value !== input.value) {
                input.value = value;
            }
        });
    }

    upgrade(document);
    $(function () {
        upgrade(document);
    });

    // Variations loaded over AJAX and download permissions added to an order.
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) {
                        upgrade(node);
                    }
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    $(document.body).on('wc-init-datepickers', function () {
        upgrade(document);
    });
    $(document).on('woocommerce_variations_loaded woocommerce_variations_added', function () {
        upgrade(document);
    });

    document.addEventListener('change', function (event) {
        var group = event.target.closest && event.target.closest('.sale_price_dates_fields');
        if (group && event.target.matches(SALE_FROM + ', ' + SALE_TO)) {
            limitSaleDates(group);
        }
    });

    // WooCommerce empties the sale dates with .val(''), and stops the click.
    document.addEventListener('click', function (event) {
        var cancel = event.target.closest && event.target.closest('.cancel_sale_schedule');
        var wrap = cancel && cancel.closest('div, table');
        if (!wrap) {
            return;
        }

        window.setTimeout(function () {
            each(wrap, '.sale_price_dates_fields', function (group) {
                each(group, DATE_FIELDS, field.refresh);
                limitSaleDates(group);
            });
        }, 0);
    }, true);

    // The order's hour and minute: their pattern and PHP's (int) need
    // English digits.
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var input = event.target;
            if (input.matches && input.matches(TIME_FIELDS) && toAsciiDigits(input.value) !== input.value) {
                input.value = toAsciiDigits(input.value);
            }
        });
    });
})(window.jQuery, window, document);
