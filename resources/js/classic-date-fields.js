/**
 * Persian Kit — Jalali date picker for the post date in the classic editor's
 * publish box and in Quick Edit.
 *
 * WordPress keeps the date in five fields: a month select and day, year,
 * hour and minute inputs (touch_time()), which post.js and Quick Edit read.
 * A nameless proxy input holding aa-mm-jj gets the picker (date-field.js),
 * and each picked date is written back into aa, mm and jj. The hour and
 * minute stay WordPress's own fields, moved into the picker's row, and
 * Persian digits typed there become English. The fields' original row is
 * hidden.
 *
 * The "Published on:" text that post.js writes shows the Jalali date.
 *
 * Depends on: jQuery, PersianKitDateField.
 */
(function ($, window, document) {
    'use strict';

    var field = window.PersianKitDateField;

    if (!$ || !field) {
        return;
    }

    var config = window.persianKitDateField || {};
    var TIME_FIELDS = 'input[name="hh"], input[name="mn"]';

    function toAsciiDigits(value) {
        return String(value).replace(/[۰-۹٠-٩]/g, function (digit) {
            var code = digit.charCodeAt(0);
            return String(code - (code >= 0x06F0 ? 0x06F0 : 0x0660));
        });
    }

    function pad(number) {
        return ('0' + number).slice(-2);
    }

    function parts(container) {
        return {
            aa: container.querySelector('[name="aa"]'),
            mm: container.querySelector('[name="mm"]'),
            jj: container.querySelector('[name="jj"]'),
            hh: container.querySelector('[name="hh"]'),
            mn: container.querySelector('[name="mn"]'),
        };
    }

    /** The date in aa, mm and jj as an ISO date, or ''. */
    function isoDate(fields) {
        var year = parseInt(toAsciiDigits(fields.aa.value), 10);
        var month = parseInt(fields.mm.value, 10);
        var day = parseInt(toAsciiDigits(fields.jj.value), 10);

        return year && month && day ? year + '-' + pad(month) + '-' + pad(day) : '';
    }

    /**
     * Puts the picker row into a fieldset holding touch_time()'s fields.
     *
     * @return {HTMLInputElement|null} The proxy input.
     */
    function build(container) {
        var original = container.querySelector('.timestamp-wrap:not(.pk-jalali-date)');
        if (!original || container.querySelector('.pk-jalali-date')) {
            return null;
        }

        var fields = parts(container);
        if (!fields.aa || !fields.mm || !fields.jj || !fields.hh || !fields.mn) {
            return null;
        }

        var row = document.createElement('div');
        row.className = 'pk-jalali-date timestamp-wrap';

        var proxy = document.createElement('input');
        proxy.type = 'text';
        proxy.value = isoDate(fields);
        proxy.setAttribute('data-persian-kit-date', '');
        proxy.setAttribute('data-persian-kit-date-hint', 'off');

        var legend = container.querySelector('legend');
        if (legend && legend.textContent.trim()) {
            proxy.setAttribute('aria-label', legend.textContent.trim());
        }

        // The time reads hour:minute from the left, also in a
        // right-to-left page. The labels hold the screen reader text
        // "Hour" and "Minute".
        var time = document.createElement('span');
        time.className = 'pk-jalali-time';
        time.dir = 'ltr';
        time.appendChild(fields.hh.closest('label') || fields.hh);
        time.appendChild(document.createTextNode(':'));
        time.appendChild(fields.mn.closest('label') || fields.mn);

        row.appendChild(proxy);
        row.appendChild(document.createTextNode(' @ '));
        row.appendChild(time);

        original.parentNode.insertBefore(row, original);
        original.style.display = 'none';

        field.upgrade(proxy);

        proxy.addEventListener('change', function () {
            var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(proxy.value);

            // WordPress needs a date: a cleared picker shows the last one.
            if (!match) {
                proxy.value = isoDate(fields);
                field.refresh(proxy);
                return;
            }

            fields.aa.value = match[1];
            fields.mm.value = match[2];
            fields.jj.value = match[3];
        });

        // Quick Edit saves on Enter (keydown) and closes on Escape (keyup).
        // With the calendar open, both keys are the calendar's: Enter picks
        // a day, Escape closes it. The picker closes itself before the key
        // bubbles out of it, so whether it was open is read on the way in.
        var picker = field.picker(proxy);
        var open = false;
        var wasOpen = false;
        var escaped = false;
        if (picker) {
            picker.addEventListener('intl-open', function () {
                open = true;
            });
            picker.addEventListener('intl-close', function () {
                open = false;
            });
            row.addEventListener('keydown', function () {
                wasOpen = open;
                escaped = false;
            }, true);
            row.addEventListener('keydown', function (event) {
                if ((event.key === 'Escape' || event.key === 'Enter') && wasOpen) {
                    event.stopPropagation();
                    escaped = event.key === 'Escape';

                    // From the text field, the picker closes on Escape only
                    // once the key reaches the document.
                    if (escaped && open) {
                        picker.close();
                    }
                }
            });
            row.addEventListener('keyup', function (event) {
                if (event.key === 'Escape' && escaped) {
                    event.stopPropagation();
                    escaped = false;
                }
            });
        }

        return proxy;
    }

    /** Re-reads aa, mm and jj after WordPress set them. */
    function reread(container) {
        var proxy = container.querySelector('.pk-jalali-date input[data-persian-kit-date]');
        if (proxy) {
            proxy.value = isoDate(parts(container));
            field.refresh(proxy);
        }
    }

    // Classic editor: the publish box.

    var formatter = null;
    try {
        formatter = new Intl.DateTimeFormat((config.locale || 'fa-IR') + '-u-ca-persian', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        });
    } catch (error) {
        formatter = null;
    }

    /** The date in the publish box's fields, as Jalali text. */
    function publishText(fields) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate(fields));
        var hour = parseInt(toAsciiDigits(fields.hh.value), 10) || 0;
        var minute = parseInt(toAsciiDigits(fields.mn.value), 10) || 0;

        if (!match || !formatter) {
            return null;
        }

        return formatter.format(new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), hour, minute));
    }

    function startPublishBox() {
        var timestampdiv = document.getElementById('timestampdiv');
        var timestamp = document.getElementById('timestamp');
        if (!timestampdiv || !build(timestampdiv)) {
            return;
        }

        var fields = parts(timestampdiv);

        // post.js rewrites the text on OK, Cancel and visibility changes.
        // "Publish immediately" has no date to show.
        function showJalali() {
            var bold = timestamp && timestamp.querySelector('b');
            var text = bold && /[0-9۰-۹٠-٩]/.test(bold.textContent) ? publishText(fields) : null;
            if (text && bold.textContent !== text) {
                bold.textContent = text;
            }
        }

        if (timestamp) {
            showJalali();
            if (typeof MutationObserver !== 'undefined') {
                new MutationObserver(showJalali).observe(timestamp, { childList: true, subtree: true, characterData: true });
            }
        }

        var picker = field.picker(timestampdiv.querySelector('.pk-jalali-date input[data-persian-kit-date]'));

        $(timestampdiv).siblings('a.edit-timestamp').on('click', function () {
            // post.js focuses the first field once the box is open; that is
            // now the hidden proxy.
            $(timestampdiv).promise().done(function () {
                if (picker) {
                    picker.focus();
                }
            });
        });

        // post.js restores aa, mm and jj from the hidden_* fields.
        $(timestampdiv).find('.cancel-timestamp').on('click', function () {
            window.setTimeout(function () {
                reread(timestampdiv);
            }, 0);
        });
    }

    // Quick Edit clones its row from #inline-edit and fills it on each
    // .editinline click, so each opening builds a row of its own.
    document.addEventListener('click', function (event) {
        if (!event.target.closest || !event.target.closest('.editinline')) {
            return;
        }

        window.setTimeout(function () {
            Array.prototype.forEach.call(document.querySelectorAll('tr.inline-edit-row:not(#inline-edit) .inline-edit-date'), build);
        }, 0);
    });

    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var input = event.target;
            if (input.matches && input.matches(TIME_FIELDS) && toAsciiDigits(input.value) !== input.value) {
                input.value = toAsciiDigits(input.value);
            }
        });
    });

    $(startPublishBox);
})(window.jQuery, window, document);
