/**
 * Persian Kit — Jalali date picker for form fields.
 *
 * Upgrades every <input data-persian-kit-date>, also those added later (ACF
 * repeater rows, forms loaded over AJAX). The input stays in the form as the
 * submitted field: it becomes a hidden input holding the Gregorian value. An
 * <intl-datepicker> without a name is inserted before it and writes each
 * change into it, so the field submits in every browser, including those
 * without form-associated custom elements, and keeps its name, id and
 * validation hooks.
 *
 * Attributes read from the input:
 *   data-persian-kit-date-format   'Y-m-d' (default), 'Ymd', 'Y-m-d H:i:s',
 *                                  which adds a time field, or day, month and
 *                                  year in any order with -, / or . between
 *                                  them, such as 'd/m/Y' (Forminator)
 *   data-persian-kit-date-type     'date' (default), 'range', 'multiple',
 *                                  'month' or 'year'; types other than 'date'
 *                                  submit the picker's own value unchanged
 *   data-persian-kit-date-min/-max ISO dates; default to the input's min/max
 *   data-persian-kit-date-disable-past, data-persian-kit-date-disable-future
 *   data-persian-kit-date-locale   default window.persianKitDateField.locale
 *   data-persian-kit-date-hint     'off' hides the typing hint under the
 *                                  field (screen readers still read it) and
 *                                  makes the picker as wide as a date
 *   required, disabled, readonly, placeholder, aria-label
 *
 * A value already in the field can also be a Jalali date in the field's
 * format (WooCommerce writes its fields with date_i18n(), which Persian Kit
 * may turn Jalali), with Persian or Arabic digits and / as separator. The
 * picker shows it, and the field keeps it until a date is picked.
 *
 * Integrations that change a field's value with a script call
 * PersianKitDateField.refresh(input) afterwards, and reach the picker, for
 * min and max, with PersianKitDateField.picker(input).
 *
 * Without JavaScript the input stays a text field, and the server converts a
 * typed Jalali date (DateInputParser).
 */
(function (window, document) {
    'use strict';

    var SELECTOR = 'input[data-persian-kit-date]';
    var PREFIX = 'data-persian-kit-date-';

    var FORMATS = {
        'Y-m-d': { pattern: /^(\d{4})-(\d{2})-(\d{2})$/, time: false },
        'Ymd': { pattern: /^(\d{4})(\d{2})(\d{2})$/, time: false },
        'Y-m-d H:i:s': { pattern: /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/, time: true },
    };

    /** Day, month and year in any order, such as d/m/Y or m.d.Y. */
    var ORDERED = /^([dmY])([-\/.])([dmY])\2([dmY])$/;

    /** Set on the input: the value it was rendered with, for copies of it. */
    var INITIAL = 'data-persian-kit-date-initial';

    var config = window.persianKitDateField || {};
    var labels = config.labels || {};

    /** Years read as Jalali in a field value. */
    var JALALI_YEARS = [1200, 1600];

    /** Input => {picker, refresh}. */
    var bound = new WeakMap();

    /** Persian and Arabic digits as English digits. */
    function toAsciiDigits(value) {
        return String(value).replace(/[\u06F0-\u06F9\u0660-\u0669]/g, function (digit) {
            var code = digit.charCodeAt(0);
            return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
        });
    }

    /**
     * How a format is read and written, or null when it is not one the
     * field knows.
     *
     * @return {{pattern?: RegExp, order?: string[], separator?: string, time: boolean}|null}
     */
    function spec(format) {
        if (Object.prototype.hasOwnProperty.call(FORMATS, format)) {
            return FORMATS[format];
        }

        var match = ORDERED.exec(format || '');
        if (!match || match[1] === match[3] || match[1] === match[4] || match[3] === match[4]) {
            return null;
        }

        return { order: [match[1], match[3], match[4]], separator: match[2], time: false };
    }

    function pad(number) {
        return ('0' + number).slice(-2);
    }

    /**
     * The year, month, day and HH:MM time of a value, as written in the
     * format. In a day-month-year format, a date written year first (as the
     * picker shows it) and any of the three separators are read too.
     *
     * @return {{year: string, month: string, day: string, time: string}|null}
     */
    function readParts(text, format) {
        var match;

        if (!format.order) {
            match = format.pattern.exec(text) || format.pattern.exec(text.replace(/\//g, '-'));
            return match ? {
                year: match[1],
                month: match[2],
                day: match[3],
                time: match[4] ? match[4] + ':' + match[5] : '',
            } : null;
        }

        match = /^(\d{1,4})[-\/.](\d{1,4})[-\/.](\d{1,4})$/.exec(text);
        if (!match) {
            return null;
        }

        var order = match[1].length === 4 ? ['Y', 'm', 'd'] : format.order;
        var found = {};
        order.forEach(function (token, index) {
            found[token] = match[index + 1];
        });

        if (found.Y.length !== 4 || found.m.length > 2 || found.d.length > 2) {
            return null;
        }

        return { year: found.Y, month: pad(found.m), day: pad(found.d), time: '' };
    }

    /**
     * A field value in the given format as an ISO date and an HH:MM time.
     * A Jalali date (years 1200 to 1600) is converted.
     *
     * @return {{date: string, time: string}|null}
     */
    function parse(value, format) {
        var text = toAsciiDigits(value || '').trim();
        var parts = readParts(text, spec(format) || FORMATS['Y-m-d']);
        if (!parts) {
            return null;
        }

        var date = parts.year + '-' + parts.month + '-' + parts.day;
        var year = Number(parts.year);
        if (year >= JALALI_YEARS[0] && year <= JALALI_YEARS[1]) {
            var calendar = window.PersianKitCalendar;
            date = calendar ? calendar.jalaliToIso(year, Number(parts.month), Number(parts.day)) : null;
            if (!date) {
                return null;
            }
        }

        return {
            date: date,
            time: parts.time,
        };
    }

    /** An ISO date and an optional HH:MM[:SS] time in the field's format. */
    function serialize(date, time, format) {
        var ordered = spec(format);
        if (ordered && ordered.order) {
            var parts = date.split('-');
            var values = { Y: parts[0], m: parts[1], d: parts[2] };

            return ordered.order.map(function (token) {
                return values[token];
            }).join(ordered.separator);
        }

        if (format === 'Ymd') {
            return date.replace(/-/g, '');
        }

        if (format === 'Y-m-d H:i:s') {
            var parts = (time || '00:00').split(':');
            return date + ' ' + parts[0] + ':' + parts[1] + ':' + (parts[2] || '00');
        }

        return date;
    }

    function option(input, name) {
        return input.getAttribute(PREFIX + name);
    }

    function upgrade(input) {
        if (bound.has(input) || !input.parentNode) {
            return;
        }

        // A copy of an upgraded field (an ACF repeater row cloned from its
        // template, a Forminator group row) brings the copied picker along,
        // unconnected, and the copied field's current value: a hidden
        // input's value is its value attribute. The copy starts from the
        // value the field was rendered with, as the inputs beside it do.
        var previous = input.previousElementSibling;
        var copiedId = '';
        if (previous && previous.classList.contains('persian-kit-date-field')) {
            var copiedPicker = previous.querySelector('intl-datepicker');
            copiedId = copiedPicker ? copiedPicker.id : '';
            previous.remove();

            if (input.hasAttribute(INITIAL)) {
                input.value = input.getAttribute(INITIAL);
            }
        }

        var type = option(input, 'type') || 'date';
        var format = spec(option(input, 'format')) ? option(input, 'format') : 'Y-m-d';
        var withTime = type === 'date' && spec(format).time;
        var initial = input.value;
        input.setAttribute(INITIAL, initial);

        var wrapper = document.createElement('span');
        wrapper.className = 'persian-kit-date-field';

        var picker = document.createElement('intl-datepicker');
        picker.className = 'persian-kit-date-picker';
        if (option(input, 'hint') === 'off') {
            picker.classList.add('persian-kit-date-picker--no-hint');
        }
        picker.setAttribute('calendar', 'persian');
        var calendar = window.PersianKitCalendar || {};
        var locale = option(input, 'locale') || config.locale || 'fa-IR';
        if (calendar.pickerLocale) {
            locale = calendar.pickerLocale(locale);
        }
        picker.setAttribute('locale', locale);
        var pickerLabels = calendar.labelsFor ? calendar.labelsFor(locale) : null;
        if (pickerLabels) {
            picker.setAttribute('labels', pickerLabels);
        }
        picker.setAttribute('allow-input', '');
        if (type !== 'date') {
            picker.setAttribute('type', type);
        }

        var min = option(input, 'min') || input.getAttribute('min');
        var max = option(input, 'max') || input.getAttribute('max');
        if (min) {
            picker.setAttribute('min', min);
        }
        if (max) {
            picker.setAttribute('max', max);
        }

        ['disable-past', 'disable-future'].forEach(function (name) {
            if (input.hasAttribute(PREFIX + name)) {
                picker.setAttribute(name, '');
            }
        });

        ['required', 'disabled', 'readonly'].forEach(function (name) {
            if (input.hasAttribute(name)) {
                picker.setAttribute(name, '');
            }
        });

        ['placeholder', 'aria-label'].forEach(function (name) {
            if (input.getAttribute(name)) {
                picker.setAttribute(name, input.getAttribute(name));
            }
        });

        // Labels pointing at the input name and focus the picker instead;
        // the input keeps its id for scripts that read the value by id.
        // Read by id: a hidden input (ACF's) has no labels. A copied row's
        // label may point at the copied picker, renamed with the row.
        if (input.id) {
            picker.id = input.id + '-picker';
            var relinkCopied = copiedId !== '' && !document.getElementById(copiedId);
            Array.prototype.forEach.call(document.querySelectorAll('label[for]'), function (label) {
                if (label.htmlFor === input.id || (relinkCopied && label.htmlFor === copiedId)) {
                    label.htmlFor = picker.id;
                }
            });
        }

        var time = null;
        if (withTime) {
            time = document.createElement('input');
            time.type = 'time';
            time.className = 'persian-kit-date-time';
            time.setAttribute('aria-label', labels.time || 'Time');
            ['disabled', 'readonly'].forEach(function (name) {
                if (input.hasAttribute(name)) {
                    time.setAttribute(name, '');
                }
            });
        }

        function show(value) {
            if (type !== 'date') {
                if (value) {
                    picker.setAttribute('value', value);
                }
                return;
            }

            var parsed = parse(value, format);
            if (parsed) {
                picker.setAttribute('value', parsed.date);
            }
            if (time) {
                time.value = parsed ? parsed.time : '';
            }
        }

        // Set while refresh() puts the field's value into the picker.
        var quiet = false;

        function sync() {
            if (quiet) {
                return;
            }

            var value = picker.value || '';

            if (type === 'date' && value !== '') {
                value = serialize(value, time ? time.value : '', format);
            }

            if (input.value === value) {
                return;
            }

            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // The field's current value into the picker and the time input,
        // without writing the field or firing events.
        function refresh() {
            var parsed = type === 'date' ? parse(input.value, format) : null;
            var pickerValue = type === 'date' ? (parsed ? parsed.date : '') : input.value;

            quiet = true;
            try {
                if (pickerValue && typeof picker.setValue === 'function') {
                    picker.setValue(pickerValue);
                } else if (!pickerValue && typeof picker.clear === 'function') {
                    picker.clear();
                }
            } finally {
                quiet = false;
            }

            if (time) {
                time.value = parsed ? parsed.time : '';
            }
        }

        show(initial);

        wrapper.appendChild(picker);
        if (time) {
            wrapper.appendChild(time);
        }
        input.parentNode.insertBefore(wrapper, input);
        input.type = 'hidden';
        bound.set(input, { picker: picker, refresh: refresh });

        picker.addEventListener('intl-change', sync);
        if (time) {
            time.addEventListener('change', sync);
        }

        // form.reset() leaves a hidden input's value alone; Contact Form 7
        // resets the form after sending it.
        if (input.form) {
            input.form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    input.value = initial;
                    refresh();
                });
            });
        }
    }

    function upgradeAll(root) {
        if (root.matches && root.matches(SELECTOR)) {
            upgrade(root);
        }
        if (root.querySelectorAll) {
            Array.prototype.forEach.call(root.querySelectorAll(SELECTOR), upgrade);
        }
    }

    function start() {
        upgradeAll(document);

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                    if (node.nodeType === 1) {
                        upgradeAll(node);
                    }
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    /** Re-reads a field's value into its picker after a script changed it. */
    function refresh(input) {
        var field = bound.get(input);
        if (field) {
            field.refresh();
        }
    }

    /** The <intl-datepicker> of an upgraded field, or null. */
    function pickerOf(input) {
        var field = bound.get(input);
        return field ? field.picker : null;
    }

    window.PersianKitDateField = {
        upgrade: upgrade,
        upgradeAll: upgradeAll,
        refresh: refresh,
        picker: pickerOf,
        parse: parse,
        serialize: serialize,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
