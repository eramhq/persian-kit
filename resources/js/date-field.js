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
 *   data-persian-kit-date-format   'Y-m-d' (default), 'Ymd', or 'Y-m-d H:i:s',
 *                                  which adds a time field
 *   data-persian-kit-date-type     'date' (default), 'range', 'multiple',
 *                                  'month' or 'year'; types other than 'date'
 *                                  submit the picker's own value unchanged
 *   data-persian-kit-date-min/-max ISO dates; default to the input's min/max
 *   data-persian-kit-date-disable-past, data-persian-kit-date-disable-future
 *   data-persian-kit-date-locale   default window.persianKitDateField.locale
 *   required, disabled, readonly, placeholder
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

    var config = window.persianKitDateField || {};
    var labels = config.labels || {};

    /** Input => its picker. */
    var bound = new WeakMap();

    /**
     * A field value in the given format as an ISO date and an HH:MM time.
     *
     * @return {{date: string, time: string}|null}
     */
    function parse(value, format) {
        var match = FORMATS[format].pattern.exec(String(value || '').trim());
        if (!match) {
            return null;
        }

        return {
            date: match[1] + '-' + match[2] + '-' + match[3],
            time: match[4] ? match[4] + ':' + match[5] : '',
        };
    }

    /** An ISO date and an optional HH:MM[:SS] time in the field's format. */
    function serialize(date, time, format) {
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
        // template) brings the copied picker along, unconnected.
        var previous = input.previousElementSibling;
        if (previous && previous.classList.contains('persian-kit-date-field')) {
            previous.remove();
        }

        var type = option(input, 'type') || 'date';
        var format = FORMATS[option(input, 'format')] ? option(input, 'format') : 'Y-m-d';
        var withTime = type === 'date' && FORMATS[format].time;
        var initial = input.value;

        var wrapper = document.createElement('span');
        wrapper.className = 'persian-kit-date-field';

        var picker = document.createElement('intl-datepicker');
        picker.className = 'persian-kit-date-picker';
        picker.setAttribute('calendar', 'persian');
        picker.setAttribute('locale', option(input, 'locale') || config.locale || 'fa-IR');
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

        if (input.placeholder) {
            picker.setAttribute('placeholder', input.placeholder);
        }

        // Labels pointing at the input name and focus the picker instead;
        // the input keeps its id for scripts that read the value by id.
        var inputLabels = input.labels ? Array.prototype.slice.call(input.labels) : [];
        if (input.id) {
            picker.id = input.id + '-picker';
            inputLabels.forEach(function (label) {
                if (label.htmlFor === input.id) {
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

        function sync() {
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

        show(initial);

        wrapper.appendChild(picker);
        if (time) {
            wrapper.appendChild(time);
        }
        input.parentNode.insertBefore(wrapper, input);
        input.type = 'hidden';
        bound.set(input, picker);

        picker.addEventListener('intl-change', sync);
        if (time) {
            time.addEventListener('change', sync);
        }

        // form.reset() leaves a hidden input's value alone; Contact Form 7
        // resets the form after sending it.
        if (input.form) {
            input.form.addEventListener('reset', function () {
                window.setTimeout(function () {
                    var parsed = type === 'date' ? parse(initial, format) : null;
                    var pickerValue = type === 'date' ? (parsed ? parsed.date : '') : initial;

                    if (pickerValue && typeof picker.setValue === 'function') {
                        picker.setValue(pickerValue);
                    } else if (!pickerValue && typeof picker.clear === 'function') {
                        picker.clear();
                    }
                    if (time) {
                        time.value = parsed ? parsed.time : '';
                    }
                    input.value = initial;
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

    window.PersianKitDateField = { upgrade: upgrade, upgradeAll: upgradeAll, parse: parse, serialize: serialize };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
