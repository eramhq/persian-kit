(function ($, Jalali) {
    'use strict';

    if (!$ || !Jalali) {
        return;
    }

    var g2j = Jalali.gregorianToJalali;
    var j2g = Jalali.jalaliToGregorian;
    var pad = Jalali.pad;
    var proxyCounter = 0;
    var ignoredProxyClasses = {
        'date-picker': true,
        'date-picker-field': true,
        'hasDatepicker': true,
        'sale_price_dates_from': true,
        'sale_price_dates_to': true,
        'pk-woo-gregorian-source': true
    };

    var dateSelectors = [
        '#_sale_price_dates_from',
        '#_sale_price_dates_to',
        '#expiry_date',
        'input[name="order_date"]',
        'input[name^="variable_sale_price_dates_from["]',
        'input[name^="variable_sale_price_dates_to["]',
        'input[name^="access_expires["]'
    ].join(', ');

    var timeSelectors = [
        'input[name="order_date_hour"]',
        'input[name="order_date_minute"]',
        'input[name="order_date_second"]'
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

    function isGregorianDate(parts) {
        return !!parts && parts.year >= 1700 && isValidGregorian(parts);
    }

    function isJalaliDate(parts) {
        return !!parts && parts.year >= 1200 && parts.year <= 1600 && isValidJalali(parts);
    }

    function buildProxyClasses($source) {
        var classNames = $.trim($source.attr('class') || '').split(/\s+/);
        var proxyClasses = [];

        $.each(classNames, function (_, className) {
            if (className && !ignoredProxyClasses[className]) {
                proxyClasses.push(className);
            }
        });

        proxyClasses.push('pk-woo-jalali-date-field');

        return $.trim(proxyClasses.join(' '));
    }

    function proxyValueFromSource(sourceValue) {
        var trimmed = $.trim(sourceValue);
        var parts;
        var jalali;

        if (trimmed === '') {
            return '';
        }

        parts = parseDate(trimmed);
        if (!isGregorianDate(parts)) {
            return trimmed;
        }

        jalali = g2j(parts.year, parts.month, parts.day);

        return formatDate({
            year: jalali[0],
            month: jalali[1],
            day: jalali[2]
        });
    }

    function syncSourceFromProxy($source, $proxy, commitInvalidRaw) {
        var rawValue = $.trim($proxy.val());
        var normalized = toAsciiDigits(rawValue);
        var parts;
        var gregorian;

        if (normalized === '') {
            $source.val('');
            return;
        }

        parts = parseDate(normalized);

        if (isGregorianDate(parts)) {
            $source.val(formatDate(parts));
            return;
        }

        if (isJalaliDate(parts)) {
            gregorian = j2g(parts.year, parts.month, parts.day);
            $source.val(formatDate({
                year: gregorian[0],
                month: gregorian[1],
                day: gregorian[2]
            }));
            return;
        }

        if (commitInvalidRaw) {
            $source.val(normalized);
        }
    }

    function normalizeTimeField($input) {
        var normalized = toAsciiDigits($.trim($input.val()));

        if ($input.val() !== normalized) {
            $input.val(normalized);
        }
    }

    function getProxyForSource($source) {
        var proxyId = $source.attr('data-pk-woo-proxy-id');

        return proxyId ? $('#' + proxyId) : $();
    }

    function bindProxyField($source, $proxy) {
        $proxy.on('input change', function () {
            syncSourceFromProxy($source, $proxy, false);
        });

        $source.on('change.pkWooDateProxy', function () {
            $proxy.val(proxyValueFromSource($source.val()));
        });
    }

    function enhanceDateField(source) {
        var $source = $(source);
        var proxyId;
        var $proxy;

        if ($source.attr('data-pk-woo-proxy-bound') === '1') {
            return;
        }

        proxyId = $source.attr('id') ? $source.attr('id') + '-pk-jalali' : 'pk-woo-date-' + (++proxyCounter);
        $proxy = $('<input />', {
            type: 'text',
            id: proxyId,
            'class': buildProxyClasses($source),
            dir: 'ltr',
            autocomplete: 'off',
            inputmode: 'numeric'
        });

        $.each(['maxlength', 'placeholder', 'pattern', 'size'], function (_, attribute) {
            var value = $source.attr(attribute);

            if (typeof value === 'string' && value !== '') {
                $proxy.attr(attribute, value);
            }
        });

        $.each(['aria-label', 'aria-describedby'], function (_, attribute) {
            var value = $source.attr(attribute);

            if (typeof value === 'string' && value !== '') {
                $proxy.attr(attribute, value);
            }
        });

        if ($source.is('[readonly]')) {
            $proxy.prop('readonly', true);
        }

        if ($source.is(':disabled')) {
            $proxy.prop('disabled', true);
        }

        $proxy.val(proxyValueFromSource($source.val()));

        $source
            .attr('data-pk-woo-proxy-bound', '1')
            .attr('data-pk-woo-proxy-id', proxyId)
            .attr('tabindex', '-1')
            .attr('aria-hidden', 'true')
            .addClass('pk-woo-gregorian-source')
            .hide()
            .after($proxy);

        bindProxyField($source, $proxy);
    }

    function enhanceDateFields(context) {
        $(dateSelectors, context || document).each(function () {
            enhanceDateField(this);
        });
    }

    function enhanceTimeFields(context) {
        $(timeSelectors, context || document).each(function () {
            var $input = $(this);

            normalizeTimeField($input);

            if ($input.attr('data-pk-woo-time-bound') === '1') {
                return;
            }

            $input.attr('data-pk-woo-time-bound', '1').on('input change blur', function () {
                normalizeTimeField($input);
            });
        });
    }

    function commitDateFields(context) {
        enhanceDateFields(context);
        enhanceTimeFields(context);

        $(dateSelectors, context || document).each(function () {
            var $source = $(this);
            var $proxy = getProxyForSource($source);

            if ($proxy.length) {
                syncSourceFromProxy($source, $proxy, true);
            }
        });
    }

    $(function () {
        enhanceDateFields(document);
        enhanceTimeFields(document);

        $(document.body).on('wc-init-datepickers', function () {
            enhanceDateFields(document);
            enhanceTimeFields(document);
        });

        $(document).on('submit', 'form#post, form[name="post"]', function () {
            commitDateFields(this);
        });

        $('#woocommerce-product-data')
            .on('woocommerce_variations_loaded woocommerce_variations_added', function () {
                enhanceDateFields(this);
                enhanceTimeFields(this);
            })
            .on('woocommerce_variations_save_variations_button woocommerce_variations_save_variations_on_submit', function () {
                commitDateFields(this);
            });
    });
})(window.jQuery, window.PersianKitJalali);
