/**
 * Persian Kit — city suggestions for Iranian addresses.
 *
 * Gives the city field a native <datalist> of the province's cities, in the
 * block and classic checkout, the classic cart's shipping calculator and My
 * Account > Addresses. The field stays a text input: customers can pick a suggestion or
 * type a village that isn't listed. The cities come from persianKitCities,
 * keyed by WooCommerce state code.
 *
 * Fields are found by their autocomplete token (or, failing that, by id), so
 * one rule covers every form. The block checkout's React code never replaces
 * the city input, so the list attribute survives its re-renders.
 */
(function () {
    'use strict';

    var data = window.persianKitCities;
    if (!data || !data.cities || typeof data.cities !== 'object') {
        return;
    }

    var cities = data.cities;
    var MARK = 'data-persian-kit-city';
    // The province the field's list was last built for; a user change away from it may clear the city.
    var STATE = 'data-persian-kit-state';
    var CITY = 'input[autocomplete~="address-level2"]:not([type="hidden"]), input#calc_shipping_city';
    var FORM = '.wc-block-components-address-form, .woocommerce-billing-fields, .woocommerce-shipping-fields, '
        + '.woocommerce-shipping-calculator, form';
    var TOKENS = { state: 'address-level1', country: 'country' };
    var count = 0;

    var listed = Object.create(null);
    Object.keys(cities).forEach(function (state) {
        (cities[state] || []).forEach(function (name) {
            listed[name] = true;
        });
    });

    /**
     * The city's state or country field: by id (billing_city → billing_state,
     * billing-city → billing-state, calc_shipping_city → calc_shipping_state),
     * else by autocomplete token in the same form.
     */
    function related(city, kind) {
        var byId = city.id && /city$/.test(city.id)
            ? document.getElementById(city.id.replace(/city$/, kind))
            : null;
        if (byId) {
            return byId;
        }

        var form = city.closest(FORM);

        return form ? form.querySelector('[autocomplete~="' + TOKENS[kind] + '"]') : null;
    }

    function value(field) {
        return field && typeof field.value === 'string' ? field.value : '';
    }

    /** The province's cities, or null when the address isn't in Iran or the province has no list. */
    function citiesFor(city) {
        if (value(related(city, 'country')) !== 'IR') {
            return null;
        }

        var list = cities[value(related(city, 'state'))];

        return Array.isArray(list) && list.length ? list : null;
    }

    function datalistFor(city) {
        var id = city.getAttribute(MARK);
        var datalist = document.getElementById(id);

        if (!datalist) {
            datalist = document.createElement('datalist');
            datalist.id = id;
            // Outside the form, so the block checkout's React tree never sees it.
            document.body.appendChild(datalist);
        }

        return datalist;
    }

    /** Point the field at its province's list, or at none. */
    function sync(city) {
        if (!city.hasAttribute(MARK)) {
            city.setAttribute(MARK, 'persian-kit-cities-' + (++count));
        }

        var state = value(related(city, 'state'));
        var list = citiesFor(city);

        city.setAttribute(STATE, state);

        if (!list) {
            city.removeAttribute('list');
            return;
        }

        var datalist = datalistFor(city);
        if (datalist.getAttribute('data-state') !== state) {
            datalist.textContent = '';
            list.forEach(function (name) {
                var option = document.createElement('option');
                option.value = name;
                datalist.appendChild(option);
            });
            datalist.setAttribute('data-state', state);
        }

        city.setAttribute('list', datalist.id);
    }

    /**
     * Empty the city when the customer picks another province and the city
     * is one of another province's cities. Typed names on no list, and names
     * the new province also has, are kept.
     */
    function clearIfStale(city) {
        var name = city.value.trim();
        if (name === '' || !listed[name] || value(related(city, 'country')) !== 'IR') {
            return;
        }

        var list = cities[value(related(city, 'state'))] || [];
        if (list.indexOf(name) !== -1) {
            return;
        }

        // React ignores a plain .value write; the native setter plus an input
        // event updates the block checkout's store. change is for classic forms.
        Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(city, '');
        city.dispatchEvent(new window.Event('input', { bubbles: true }));
        city.dispatchEvent(new window.Event('change', { bubbles: true }));
    }

    function scan() {
        Array.prototype.forEach.call(document.querySelectorAll(CITY), function (city) {
            if (!city.hasAttribute(MARK)) {
                sync(city);
            }
        });
    }

    function handled() {
        return document.querySelectorAll('input[' + MARK + ']');
    }

    function onChange(target) {
        Array.prototype.forEach.call(handled(), function (city) {
            if (target === related(city, 'state')) {
                if (value(target) !== city.getAttribute(STATE)) {
                    clearIfStale(city);
                }
                sync(city);
            } else if (target === related(city, 'country')) {
                sync(city);
            }
        });
    }

    // selectWoo and WooCommerce's classic scripts fire jQuery-only change
    // events; a jQuery handler gets those and native ones alike.
    var $ = window.jQuery;
    if ($) {
        $(document).on('change', function (event) {
            onChange(event.target);
        });
        // WooCommerce redraws the classic state field after a country change.
        $(document.body).on('country_to_state_changed', function () {
            Array.prototype.forEach.call(handled(), sync);
        });
    } else {
        document.addEventListener('change', function (event) {
            onChange(event.target);
        });
    }

    // React changes the province or country without events (a country change
    // resets the province, a saved address loads), so the list is rebuilt
    // whenever the customer comes to the city field. This never clears it.
    document.addEventListener('focusin', function (event) {
        var target = event.target;
        if (target && target.matches && target.matches(CITY)) {
            sync(target);
        }
    });

    // Forms that appear later: the block checkout's billing form when "use
    // same address" is unticked, an address card's Edit button, the classic
    // cart's shipping calculator.
    var pending = false;
    var later = window.requestAnimationFrame || function (callback) {
        return window.setTimeout(callback, 16);
    };
    if (window.MutationObserver) {
        new window.MutationObserver(function (mutations) {
            if (pending) {
                return;
            }
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) {
                    pending = true;
                    later(function () {
                        pending = false;
                        scan();
                    });
                    return;
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
}());
