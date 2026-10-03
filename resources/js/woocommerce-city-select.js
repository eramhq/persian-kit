/**
 * Persian Kit — city suggestions for Iranian addresses.
 *
 * Gives the city field a list of the province's cities (an ARIA 1.2
 * combobox), in the block and classic checkout, the classic cart's shipping
 * calculator and My Account > Addresses. The field stays a text input:
 * customers can pick a suggestion or type a village that isn't listed. The
 * cities come from persianKitCities, keyed by WooCommerce state code.
 *
 * Matching ignores how a name is typed: spaces and half-spaces, Arabic ي
 * and ك, ئ, گ, hamza and the like (see key(), which CityNames::key() in PHP
 * mirrors). So قائمشهر finds قایم شهر, and the server saves it that way.
 *
 * Fields are found by their autocomplete token (or, failing that, by id), so
 * one rule covers every form. The block checkout's React code never replaces
 * the city input and keeps attributes it doesn't render, so the combobox
 * attributes survive its re-renders. Each list sits outside the form, at
 * the end of the body, so React never sees it.
 */
(function () {
    'use strict';

    var data = window.persianKitCities;
    if (!data || !data.cities || typeof data.cities !== 'object') {
        return;
    }

    var cities = data.cities;
    var i18n = data.i18n || {};
    var MARK = 'data-persian-kit-city';
    // The province the field's list was last built for; a user change away from it may clear the city.
    var STATE = 'data-persian-kit-state';
    var LIST = 'data-persian-kit-list';
    var CITY = 'input[autocomplete~="address-level2"]:not([type="hidden"]), input#calc_shipping_city';
    var FORM = '.wc-block-components-address-form, .woocommerce-billing-fields, .woocommerce-shipping-fields, '
        + '.woocommerce-shipping-calculator, form';
    var TOKENS = { state: 'address-level1', country: 'country' };
    var MAX = 8;
    var count = 0;

    // Letters with more than one spelling, folded to one; Persian and Arabic digits to ASCII.
    // ي ى ئ → ی, ك گ → ک, ة ۀ ە ھ ہ → ه, أ إ آ ٱ → ا, ؤ → و.
    var FOLD = {
        '\u064A': '\u06CC', '\u0649': '\u06CC', '\u0626': '\u06CC',
        '\u0643': '\u06A9', '\u06AF': '\u06A9',
        '\u0629': '\u0647', '\u06C0': '\u0647', '\u06D5': '\u0647', '\u06BE': '\u0647', '\u06C1': '\u0647',
        '\u0623': '\u0627', '\u0625': '\u0627', '\u0622': '\u0627', '\u0671': '\u0627',
        '\u0624': '\u0648'
    };
    var FOLD_RE = /[\u064A\u0649\u0626\u0643\u06AF\u0629\u06C0\u06D5\u06BE\u06C1\u0623\u0625\u0622\u0671\u0624\u06F0-\u06F9\u0660-\u0669]/g;
    // Spaces, zero-width and direction marks (ZWNJ included), kashida,
    // tashkeel, hamza, hyphens and dashes, and . ( ) /. Kept in step with CityNames::STRIP.
    var STRIP_RE = /[\t\n\u000B\f\r \u00A0\u1680\u2000-\u200F\u2028-\u202F\u205F\u2060-\u2069\u3000\uFEFF\u0640\u064B-\u065F\u0670\u0621\-\u2010-\u2015\u2212\uFE58\uFE63\uFF0D.()\/]/g;
    // Where a word starts, for prefix matches: after a space, ZWNJ, (, / or -.
    var WORD_BREAK = /[ \u00A0\u200C(\/\-]/;

    function fold(letter) {
        var code = letter.charCodeAt(0);
        if (code >= 0x06F0 && code <= 0x06F9) {
            return String(code - 0x06F0);
        }
        if (code >= 0x0660 && code <= 0x0669) {
            return String(code - 0x0660);
        }

        return FOLD[letter];
    }

    function key(name) {
        return String(name).replace(FOLD_RE, fold).replace(STRIP_RE, '');
    }

    var listedKeys = Object.create(null);
    Object.keys(cities).forEach(function (state) {
        (cities[state] || []).forEach(function (name) {
            listedKeys[key(name)] = true;
        });
    });

    /** Each province's names with their keys, built when first needed. */
    var entries = Object.create(null);

    function entriesFor(state) {
        if (!entries[state]) {
            entries[state] = (cities[state] || []).map(function (name) {
                var starts = [key(name)];
                for (var i = 1; i < name.length; i++) {
                    if (WORD_BREAK.test(name.charAt(i - 1)) && !WORD_BREAK.test(name.charAt(i))) {
                        starts.push(key(name.slice(i)));
                    }
                }

                return { name: name, key: starts[0], starts: starts };
            });
        }

        return entries[state];
    }

    /**
     * Names for what was typed, best first: the same name, then names (or
     * a word in them) that start with it, then, from two letters, names
     * that contain it. List order within each; the capital comes first.
     */
    function matches(state, query) {
        var list = entriesFor(state);
        var typed = key(query);

        if (typed === '') {
            return list.map(function (entry) {
                return entry.name;
            });
        }

        var ranks = [[], [], []];
        list.forEach(function (entry) {
            if (entry.key === typed) {
                ranks[0].push(entry.name);
            } else if (entry.starts.some(function (start) {
                return start.indexOf(typed) === 0;
            })) {
                ranks[1].push(entry.name);
            } else if (typed.length >= 2 && entry.key.indexOf(typed) !== -1) {
                ranks[2].push(entry.name);
            }
        });

        return ranks[0].concat(ranks[1], ranks[2]).slice(0, MAX);
    }

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

    /** The province's code when the address is in Iran and the province has a list, else ''. */
    function listedState(city) {
        if (value(related(city, 'country')) !== 'IR') {
            return '';
        }

        var state = value(related(city, 'state'));
        var list = cities[state];

        return Array.isArray(list) && list.length ? state : '';
    }

    // React ignores a plain .value write; the native setter plus an input
    // event updates the block checkout's store. change is for classic forms.
    var writing = false;

    function write(city, name) {
        writing = true;
        try {
            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(city, name);
            city.dispatchEvent(new window.Event('input', { bubbles: true }));
            city.dispatchEvent(new window.Event('change', { bubbles: true }));
        } finally {
            writing = false;
        }
    }

    var status = null;

    /** Tells screen readers how many cities the list shows. */
    function announce(text) {
        if (!status) {
            status = document.createElement('div');
            status.className = 'persian-kit-city-status';
            status.setAttribute('role', 'status');
            status.setAttribute(LIST, '');
            document.body.appendChild(status);
        }
        status.textContent = text || '';
    }

    function resultsText(total) {
        if (total === 1 && i18n.oneResult) {
            return i18n.oneResult;
        }

        return String(i18n.results || '%d').replace('%d', String(total));
    }

    /** The open field's list, if any; one is open at a time. */
    var openCombo = null;
    var combos = [];

    function Combo(city) {
        var id = 'persian-kit-cities-' + (++count);
        var listbox = document.createElement('ul');

        listbox.id = id;
        listbox.className = 'persian-kit-city-list';
        listbox.setAttribute('role', 'listbox');
        listbox.setAttribute(LIST, '');
        if (i18n.label) {
            listbox.setAttribute('aria-label', i18n.label);
        }
        listbox.hidden = true;
        document.body.appendChild(listbox);

        this.city = city;
        this.listbox = listbox;
        this.names = [];
        this.active = -1;
        this.state = '';
        // A press in the list, until its click: a tap can still blur the field first.
        this.pressing = false;

        var combo = this;

        // Keep the focus in the field, so it isn't validated on blur before the pick.
        function keepFocus(event) {
            event.preventDefault();
            combo.pressing = true;
        }
        listbox.addEventListener('pointerdown', keepFocus);
        listbox.addEventListener('mousedown', keepFocus);
        listbox.addEventListener('click', function (event) {
            var option = event.target.closest ? event.target.closest('[role="option"]') : null;
            combo.pressing = false;
            if (option && listbox.contains(option)) {
                combo.pick(Number(option.getAttribute('data-index')));
            }
        });

        city.setAttribute(MARK, id);
        city.addEventListener('input', function (event) {
            combo.onInput(event);
        });
        city.addEventListener('keydown', function (event) {
            combo.onKeydown(event);
        });
        city.addEventListener('blur', function () {
            if (!combo.pressing) {
                combo.close();
            }
        });

        combos.push(this);
    }

    Combo.prototype.enable = function (state) {
        var city = this.city;

        if (state !== this.state) {
            this.close();
            this.state = state;
        }

        if (!state) {
            ['role', 'aria-autocomplete', 'aria-expanded', 'aria-controls', 'aria-activedescendant'].forEach(function (name) {
                city.removeAttribute(name);
            });
            return;
        }

        city.setAttribute('role', 'combobox');
        city.setAttribute('aria-autocomplete', 'list');
        city.setAttribute('aria-controls', this.listbox.id);
        if (!city.hasAttribute('aria-expanded')) {
            city.setAttribute('aria-expanded', 'false');
        }
    };

    Combo.prototype.onInput = function (event) {
        if (writing || !this.state) {
            return;
        }

        // Typing, deleting or pasting has an inputType. Autofill fires a plain
        // Event, and a pick from the browser's own suggestions replaces the text.
        var typed = event.inputType && event.inputType !== 'insertReplacementText';
        if (!typed) {
            this.close();
            return;
        }

        if (this.city.value.trim() === '') {
            this.close();
            announce('');
            return;
        }

        this.show(this.city.value, -1);
    };

    Combo.prototype.onKeydown = function (event) {
        if (!this.state || event.altKey && event.key !== 'ArrowDown' || event.ctrlKey || event.metaKey) {
            return;
        }

        var open = !this.listbox.hidden;
        var handled = true;

        switch (event.key) {
            case 'ArrowDown':
                if (open) {
                    this.move(1);
                } else {
                    this.show(this.city.value, 0);
                }
                break;
            case 'ArrowUp':
                if (open) {
                    this.move(-1);
                } else {
                    this.show(this.city.value, -2);
                }
                break;
            case 'Escape':
                handled = open;
                this.close();
                break;
            case 'Enter':
                handled = open && this.active >= 0;
                if (handled) {
                    this.pick(this.active);
                }
                break;
            case 'Tab':
                this.close();
                handled = false;
                break;
            default:
                handled = false;
        }

        // Stops the form submitting on Enter, and keeps the classic
        // checkout from updating on every arrow press.
        if (handled) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    /**
     * Opens the list with the names for $query. $active is the option to
     * mark: -1 none, -2 the last.
     */
    Combo.prototype.show = function (query, active) {
        var names = matches(this.state, query);
        var listbox = this.listbox;
        var id = listbox.id;

        this.names = names;
        listbox.textContent = '';

        if (!names.length) {
            this.close();
            announce(i18n.noResults || '');
            return;
        }

        names.forEach(function (name, index) {
            var option = document.createElement('li');
            option.id = id + '-' + index;
            option.className = 'persian-kit-city-option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.setAttribute('data-index', String(index));
            // The filter can add any string; never parsed as HTML.
            option.textContent = name;
            listbox.appendChild(option);
        });

        if (openCombo && openCombo !== this) {
            openCombo.close();
        }
        openCombo = this;

        listbox.hidden = false;
        this.city.setAttribute('aria-expanded', 'true');
        this.style();
        this.position();
        this.activate(active === -2 ? names.length - 1 : active);
        announce(resultsText(names.length));
    };

    Combo.prototype.move = function (step) {
        var total = this.names.length;
        this.activate(this.active < 0 && step < 0 ? total - 1 : (this.active + step + total) % total);
    };

    Combo.prototype.activate = function (index) {
        var city = this.city;
        var options = this.listbox.children;

        if (this.active >= 0 && options[this.active]) {
            options[this.active].setAttribute('aria-selected', 'false');
        }

        this.active = index >= 0 && index < options.length ? index : -1;

        if (this.active < 0) {
            city.removeAttribute('aria-activedescendant');
            return;
        }

        var option = options[this.active];
        option.setAttribute('aria-selected', 'true');
        city.setAttribute('aria-activedescendant', option.id);
        if (option.scrollIntoView) {
            option.scrollIntoView({ block: 'nearest' });
        }
    };

    Combo.prototype.pick = function (index) {
        var name = this.names[index];
        if (typeof name !== 'string') {
            return;
        }

        this.close();
        write(this.city, name);
    };

    Combo.prototype.close = function () {
        if (this.listbox.hidden && this.active < 0) {
            return;
        }

        this.listbox.hidden = true;
        this.active = -1;
        this.city.removeAttribute('aria-activedescendant');
        if (this.city.hasAttribute('role')) {
            this.city.setAttribute('aria-expanded', 'false');
        }
        if (openCombo === this) {
            openCombo = null;
        }
    };

    /** The field's font and colours, so the list looks like part of the form. */
    Combo.prototype.style = function () {
        if (!window.getComputedStyle) {
            return;
        }

        var computed = window.getComputedStyle(this.city);
        var style = this.listbox.style;

        style.fontFamily = computed.fontFamily;
        style.fontSize = computed.fontSize;
        style.color = computed.color;
        if (computed.backgroundColor && !/^(transparent|rgba\(.*,\s*0\))$/.test(computed.backgroundColor)) {
            style.backgroundColor = computed.backgroundColor;
        }
    };

    /** Under the field, or above it when there's more room there. */
    Combo.prototype.position = function () {
        var rect = this.city.getBoundingClientRect();
        var style = this.listbox.style;
        var viewport = window.visualViewport;
        var bottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
        var top = viewport ? viewport.offsetTop : 0;

        style.left = rect.left + 'px';
        style.width = rect.width + 'px';
        style.top = rect.bottom + 'px';
        style.bottom = '';

        var height = this.listbox.offsetHeight;
        if (rect.bottom + height > bottom && rect.top - top > bottom - rect.bottom) {
            style.top = '';
            style.bottom = (window.innerHeight - rect.top) + 'px';
        }
    };

    function comboFor(city) {
        return city.persianKitCombo || (city.persianKitCombo = new Combo(city));
    }

    /** Points the field at its province's list, or at none. */
    function sync(city) {
        var state = listedState(city);

        city.setAttribute(STATE, value(related(city, 'state')));
        comboFor(city).enable(state);
    }

    /**
     * Empty the city when the customer picks another province and the city
     * is one of another province's cities. Typed names on no list, and names
     * the new province also has, are kept.
     */
    function clearIfStale(city) {
        var typed = key(city.value.trim());
        if (typed === '' || !listedKeys[typed] || value(related(city, 'country')) !== 'IR') {
            return;
        }

        var list = cities[value(related(city, 'state'))] || [];
        for (var i = 0; i < list.length; i++) {
            if (key(list[i]) === typed) {
                return;
            }
        }

        write(city, '');
    }

    function scan() {
        // Fields React or WooCommerce took out of the page take their lists with them.
        combos = combos.filter(function (combo) {
            if (document.body.contains(combo.city)) {
                return true;
            }
            combo.close();
            combo.listbox.parentNode.removeChild(combo.listbox);
            combo.city.persianKitCombo = null;

            return false;
        });

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
    // The list never opens on focus.
    document.addEventListener('focusin', function (event) {
        var target = event.target;
        if (target && target.matches && target.matches(CITY)) {
            sync(target);
        }
    });

    // A click or tap anywhere but the field and its list closes it.
    function closeOutside(event) {
        if (openCombo && event.target !== openCombo.city && !openCombo.listbox.contains(event.target)) {
            openCombo.pressing = false;
            openCombo.close();
        }
    }
    document.addEventListener('pointerdown', closeOutside, true);
    document.addEventListener('mousedown', closeOutside, true);

    var later = window.requestAnimationFrame || function (callback) {
        return window.setTimeout(callback, 16);
    };

    // The list is fixed under the field: it follows the page and the
    // on-screen keyboard.
    var placing = false;
    function reposition() {
        if (!openCombo || placing) {
            return;
        }
        placing = true;
        later(function () {
            placing = false;
            if (openCombo) {
                openCombo.position();
            }
        });
    }
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', reposition);
        window.visualViewport.addEventListener('scroll', reposition);
    }

    /** Our lists and the status region, whose changes the observer ignores. */
    function ours(node) {
        var element = node && node.nodeType === 1 ? node : node && node.parentNode;

        return !!(element && element.closest && element.closest('[' + LIST + ']'));
    }

    // Forms that appear later: the block checkout's billing form when "use
    // same address" is unticked, an address card's Edit button, the classic
    // cart's shipping calculator.
    var pending = false;
    if (window.MutationObserver) {
        new window.MutationObserver(function (mutations) {
            if (pending) {
                return;
            }
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (ours(mutation.target)) {
                    continue;
                }
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    if (!ours(mutation.addedNodes[j])) {
                        pending = true;
                        later(function () {
                            pending = false;
                            scan();
                        });
                        return;
                    }
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
