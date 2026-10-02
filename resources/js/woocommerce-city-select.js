/**
 * Persian Kit — city dropdown for Iranian addresses in the classic checkout
 * and My Account > Addresses.
 *
 * Refills the city select when the province changes, and swaps it for a text
 * input when another country is chosen (and back again for Iran). The cities
 * come from persianKitCities, keyed by WooCommerce state code.
 */
(function ($) {
    'use strict';

    if (!$ || typeof window.persianKitCities === 'undefined') {
        return;
    }

    var cities = window.persianKitCities.cities || {};
    var placeholder = window.persianKitCities.placeholder || '';

    function copyAttributes($from, $to) {
        $.each(['id', 'name', 'autocomplete', 'placeholder', 'data-placeholder'], function (i, attr) {
            var value = $from.attr(attr);
            if (typeof value !== 'undefined') {
                $to.attr(attr, value);
            }
        });
    }

    function destroySelectWoo($field) {
        if ($field.data('select2') && $.fn.selectWoo) {
            $field.selectWoo('destroy');
        }
    }

    function toText($city) {
        if ($city.is('input')) {
            return $city;
        }

        var value = $city.val() || '';
        var $input = $('<input type="text" class="input-text" />');
        copyAttributes($city, $input);
        $input.removeAttr('data-placeholder').val(value);

        destroySelectWoo($city);
        $city.replaceWith($input);

        return $input;
    }

    function toSelect($city, list) {
        var value = $city.val() || '';
        var $select = $city;

        if (!$city.is('select')) {
            $select = $('<select class="select"></select>');
            copyAttributes($city, $select);
            $select.removeAttr('placeholder');
            $city.replaceWith($select);
        }

        // A city typed or saved before stays only if the province has it.
        var keep = $.inArray(value, list) !== -1;

        $select.empty().append($('<option value=""></option>').text(placeholder));
        $.each(list, function (i, name) {
            $select.append($('<option></option>').attr('value', name).text(name));
        });
        $select.val(keep ? value : '');

        if ($.fn.selectWoo && !$select.data('select2')) {
            $select.attr('data-placeholder', placeholder).selectWoo({ width: '100%' });
        }

        return $select;
    }

    function refresh(group) {
        var $city = $('#' + group + '_city');
        if (!$city.length) {
            return;
        }

        var country = $('#' + group + '_country').val();
        if (country !== 'IR') {
            toText($city);
            return;
        }

        var state = $('#' + group + '_state').val();
        var list = cities[state] || [];
        var current = $city.val() || '';

        // The server lists a saved city the province lacks; keep it on load.
        if (current !== '' && $.inArray(current, list) === -1 && $city.is('select') && !$city.data('persianKitReady')) {
            list = list.concat([current]);
        }

        toSelect($city, list).data('persianKitReady', true);
    }

    function refreshAll() {
        refresh('billing');
        refresh('shipping');
    }

    // WooCommerce redraws the state field after a country change, then fires this.
    $(document.body).on('country_to_state_changed', refreshAll);
    $(document.body).on('change', '#billing_state, #shipping_state', function () {
        refresh(this.id.replace(/_state$/, ''));
    });

    $(refreshAll);
}(window.jQuery));
