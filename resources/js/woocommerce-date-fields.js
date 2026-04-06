(function ($, Jalali) {
    'use strict';

    if (!$ || !Jalali) {
        return;
    }

    var g2j = Jalali.gregorianToJalali;
    var j2g = Jalali.jalaliToGregorian;
    var pad = Jalali.pad;

    var selectors = [
        '#_sale_price_dates_from',
        '#_sale_price_dates_to',
        '#expiry_date',
        'input[name="order_date"]',
        'input[name^="variable_sale_price_dates_from["]',
        'input[name^="variable_sale_price_dates_to["]'
    ].join(', ');

    function toAsciiDigits(value) {
        return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) {
            var map = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
            };

            return map[digit] || digit;
        });
    }

    function parseDate(value) {
        var normalized = toAsciiDigits($.trim(value));
        var match = normalized.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (!match) {
            return null;
        }

        return {
            year: parseInt(match[1], 10),
            month: parseInt(match[2], 10),
            day: parseInt(match[3], 10)
        };
    }

    function formatDate(parts) {
        return [
            parts.year,
            pad(parts.month),
            pad(parts.day)
        ].join('-');
    }

    function isValidGregorian(parts) {
        if (!parts) {
            return false;
        }

        var date = new Date(parts.year, parts.month - 1, parts.day);

        return date.getFullYear() === parts.year
            && date.getMonth() === parts.month - 1
            && date.getDate() === parts.day;
    }

    function isValidJalali(parts) {
        if (!parts) {
            return false;
        }

        if (parts.month < 1 || parts.month > 12 || parts.day < 1) {
            return false;
        }

        return parts.day <= Jalali.jalaliMonthLength(parts.month, parts.year);
    }

    function destroyGregorianPicker($input) {
        if (typeof $input.datepicker !== 'function') {
            return;
        }

        if (!$input.hasClass('hasDatepicker')) {
            return;
        }

        try {
            $input.datepicker('destroy');
        } catch (error) {
            return;
        }

        $input.removeClass('hasDatepicker');
    }

    function convertInputToJalali(input) {
        var $input = $(input);
        var parts = parseDate($input.val());

        if (!parts || parts.year < 1700 || !isValidGregorian(parts)) {
            destroyGregorianPicker($input);
            return;
        }

        var jalali = g2j(parts.year, parts.month, parts.day);
        $input.val(formatDate({
            year: jalali[0],
            month: jalali[1],
            day: jalali[2]
        }));
        $input.attr('data-pk-woo-calendar', 'jalali');

        destroyGregorianPicker($input);
    }

    function convertInputToGregorian(input) {
        var $input = $(input);
        var parts = parseDate($input.val());

        if (!parts || parts.year < 1200 || parts.year > 1600 || !isValidJalali(parts)) {
            return;
        }

        var gregorian = j2g(parts.year, parts.month, parts.day);
        $input.val(formatDate({
            year: gregorian[0],
            month: gregorian[1],
            day: gregorian[2]
        }));
    }

    function convertVisibleWooDates(context) {
        $(selectors, context || document).each(function () {
            convertInputToJalali(this);
        });
    }

    function convertWooDatesForSave(context) {
        $(selectors, context || document).each(function () {
            convertInputToGregorian(this);
        });
    }

    $(function () {
        convertVisibleWooDates(document);

        $(document).on('submit', 'form#post, form[name="post"]', function () {
            convertWooDatesForSave(this);
        });

        $('#woocommerce-product-data')
            .on('woocommerce_variations_loaded woocommerce_variations_added', function () {
                convertVisibleWooDates(this);
            })
            .on('woocommerce_variations_save_variations_button woocommerce_variations_save_variations_on_submit', function () {
                convertWooDatesForSave(this);
            });
    });
})(window.jQuery, window.PersianKitJalali);
