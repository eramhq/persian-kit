/**
 * Persian Kit — Gutenberg Jalali Date Editor
 *
 * Replaces the native Gregorian schedule UI in the block editor sidebar
 * with Jalali (Shamsi) date inputs via DOM overlay.
 *
 * Two subsystems:
 *   A. Label Override — replaces the sidebar date button text with Jalali
 *   B. Popover Interceptor — hides native DateTimePicker, injects Jalali form
 *
 * Depends on: wp-data, wp-components, PersianKitJalali
 */
(function (wp, Jalali) {
    'use strict';

    if (!wp || !wp.data || !Jalali) {
        return;
    }

    var g2j = Jalali.gregorianToJalali;
    var j2g = Jalali.jalaliToGregorian;
    var MONTHS = Jalali.JALALI_MONTHS;
    var pad = Jalali.pad;
    var jalaliMonthLength = Jalali.jalaliMonthLength;

    var isDispatching = false;
    var isInjecting = false;
    var lastDateKey = '';
    /** Tracks whether the schedule popover is open, to skip DOM queries in subscribe. */
    var popoverOpen = false;
    /** Active content observer, disconnected when popover closes. */
    var activeContentObserver = null;

    function getStoreDate() {
        var editor = wp.data.select('core/editor');
        return {
            date: editor.getEditedPostAttribute('date'),
            isFloating: editor.isEditedPostDateFloating()
        };
    }

    function jalaliLabel(d) {
        var j = g2j(d.getFullYear(), d.getMonth() + 1, d.getDate());
        return j[2] + ' ' + MONTHS[j[1]] + ' ' + j[0] + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function currentJalaliParts() {
        var info = getStoreDate();
        var d;
        if (!info.date || info.isFloating) {
            d = new Date();
        } else {
            d = new Date(info.date);
            if (isNaN(d.getTime())) {
                d = new Date();
            }
        }
        var j = g2j(d.getFullYear(), d.getMonth() + 1, d.getDate());
        return { jy: j[0], jm: j[1], jd: j[2], hh: d.getHours(), mn: d.getMinutes() };
    }

    /**
     * Cache form field elements to avoid repeated querySelector calls.
     * Returns { jy, jm, jd, hh, mn } element references.
     */
    function getFormFields(formRoot) {
        return {
            jy: formRoot.querySelector('[data-field="jy"]'),
            jm: formRoot.querySelector('[data-field="jm"]'),
            jd: formRoot.querySelector('[data-field="jd"]'),
            hh: formRoot.querySelector('[data-field="hh"]'),
            mn: formRoot.querySelector('[data-field="mn"]')
        };
    }

    // ── A. Label Override ────────────────────────

    function updateLabel() {
        if (isDispatching) return;

        var info = getStoreDate();
        var key = info.isFloating ? '__floating__' : (info.date || '');
        if (key === lastDateKey) return;
        lastDateKey = key;

        // Run after React's synchronous render
        setTimeout(function () {
            var btn = document.querySelector('.editor-post-schedule__dialog-toggle');
            if (!btn) return;

            if (info.isFloating || !info.date) {
                btn.textContent = 'هم‌اکنون';
            } else {
                var d = new Date(info.date);
                if (!isNaN(d.getTime())) {
                    btn.textContent = jalaliLabel(d);
                }
            }
        }, 0);
    }

    wp.data.subscribe(updateLabel);

    // ── B. Popover Interceptor ───────────────────

    function buildForm(parts) {
        var form = document.createElement('div');
        form.className = 'pk-gutenberg-jalali-form';

        var dateLegend = document.createElement('legend');
        dateLegend.className = 'pk-jalali-legend';
        dateLegend.textContent = 'تاریخ';

        var dateFieldset = document.createElement('fieldset');
        dateFieldset.className = 'pk-jalali-fieldset';
        dateFieldset.appendChild(dateLegend);

        var dateRow = document.createElement('div');
        dateRow.className = 'pk-jalali-row';

        var yearInput = document.createElement('input');
        yearInput.type = 'number';
        yearInput.className = 'pk-jalali-input pk-jalali-year';
        yearInput.setAttribute('data-field', 'jy');
        yearInput.min = 1300;
        yearInput.max = 1500;
        yearInput.value = parts.jy;

        var monthSelect = document.createElement('select');
        monthSelect.className = 'pk-jalali-input pk-jalali-month';
        monthSelect.setAttribute('data-field', 'jm');
        for (var m = 1; m <= 12; m++) {
            var opt = document.createElement('option');
            opt.value = m;
            opt.textContent = MONTHS[m];
            if (m === parts.jm) opt.selected = true;
            monthSelect.appendChild(opt);
        }

        var dayInput = document.createElement('input');
        dayInput.type = 'number';
        dayInput.className = 'pk-jalali-input pk-jalali-day';
        dayInput.setAttribute('data-field', 'jd');
        dayInput.min = 1;
        dayInput.max = jalaliMonthLength(parts.jm, parts.jy);
        dayInput.value = parts.jd;

        dateRow.appendChild(yearInput);
        dateRow.appendChild(monthSelect);
        dateRow.appendChild(dayInput);
        dateFieldset.appendChild(dateRow);

        var timeLegend = document.createElement('legend');
        timeLegend.className = 'pk-jalali-legend';
        timeLegend.textContent = 'ساعت';

        var timeFieldset = document.createElement('fieldset');
        timeFieldset.className = 'pk-jalali-fieldset';
        timeFieldset.appendChild(timeLegend);

        var timeRow = document.createElement('div');
        timeRow.className = 'pk-jalali-row';

        var hourInput = document.createElement('input');
        hourInput.type = 'number';
        hourInput.className = 'pk-jalali-input pk-jalali-hour';
        hourInput.setAttribute('data-field', 'hh');
        hourInput.min = 0;
        hourInput.max = 23;
        hourInput.value = pad(parts.hh);

        var sep = document.createElement('span');
        sep.textContent = ':';
        sep.className = 'pk-jalali-separator';

        var minuteInput = document.createElement('input');
        minuteInput.type = 'number';
        minuteInput.className = 'pk-jalali-input pk-jalali-minute';
        minuteInput.setAttribute('data-field', 'mn');
        minuteInput.min = 0;
        minuteInput.max = 59;
        minuteInput.value = pad(parts.mn);

        timeRow.appendChild(hourInput);
        timeRow.appendChild(sep);
        timeRow.appendChild(minuteInput);
        timeFieldset.appendChild(timeRow);

        form.appendChild(dateFieldset);
        form.appendChild(timeFieldset);

        return form;
    }

    function syncToStore(formRoot) {
        var f = getFormFields(formRoot);
        var jy = parseInt(f.jy.value, 10);
        var jm = parseInt(f.jm.value, 10);
        var jd = parseInt(f.jd.value, 10);
        var hh = parseInt(f.hh.value, 10);
        var mn = parseInt(f.mn.value, 10);

        if (isNaN(jy) || isNaN(jm) || isNaN(jd) || isNaN(hh) || isNaN(mn)) return;

        jm = Math.max(1, Math.min(12, jm));
        var maxDay = jalaliMonthLength(jm, jy);
        jd = Math.max(1, Math.min(maxDay, jd));
        hh = Math.max(0, Math.min(23, hh));
        mn = Math.max(0, Math.min(59, mn));

        f.jd.max = maxDay;
        if (parseInt(f.jd.value, 10) > maxDay) {
            f.jd.value = maxDay;
        }

        var greg = j2g(jy, jm, jd);
        var iso = greg[0] + '-' + pad(greg[1]) + '-' + pad(greg[2]) +
            'T' + pad(hh) + ':' + pad(mn) + ':00';

        isDispatching = true;
        wp.data.dispatch('core/editor').editPost({ date: iso });

        setTimeout(function () {
            isDispatching = false;
            lastDateKey = iso;
            var btn = document.querySelector('.editor-post-schedule__dialog-toggle');
            if (btn) {
                var d = new Date(iso);
                if (!isNaN(d.getTime())) {
                    btn.textContent = jalaliLabel(d);
                }
            }
        }, 0);
    }

    function updateFormFromStore(formRoot) {
        var parts = currentJalaliParts();
        var f = getFormFields(formRoot);
        f.jy.value = parts.jy;
        f.jm.value = parts.jm;
        f.jd.value = parts.jd;
        f.jd.max = jalaliMonthLength(parts.jm, parts.jy);
        f.hh.value = pad(parts.hh);
        f.mn.value = pad(parts.mn);
    }

    function bindFormEvents(formRoot) {
        var handler = function () { syncToStore(formRoot); };
        var inputs = formRoot.querySelectorAll('input, select');
        for (var i = 0; i < inputs.length; i++) {
            // Use 'input' for number fields (fires on each keystroke/arrow),
            // 'change' for select (fires on selection).
            inputs[i].addEventListener(inputs[i].type === 'number' ? 'input' : 'change', handler);
        }
    }

    /**
     * Inject the Jalali form into a content wrapper, hiding the native picker.
     * Used for both initial injection and re-injection after React re-renders.
     */
    function injectForm(contentWrapper) {
        var nativePicker = contentWrapper.querySelector('.components-datetime');
        if (nativePicker) {
            nativePicker.classList.add('pk-hidden');
        }

        var parts = currentJalaliParts();
        var form = buildForm(parts);
        bindFormEvents(form);

        if (nativePicker) {
            contentWrapper.insertBefore(form, nativePicker);
        } else {
            contentWrapper.appendChild(form);
        }
    }

    function handlePopoverOpen(dialog) {
        var contentWrapper = dialog.querySelector('.block-editor-publish-date-time-picker');
        if (!contentWrapper) return;

        var nativePicker = contentWrapper.querySelector('.components-datetime');
        if (nativePicker) {
            nativePicker.classList.add('pk-hidden');
        }

        var existingForm = contentWrapper.querySelector('.pk-gutenberg-jalali-form');
        if (existingForm) {
            updateFormFromStore(existingForm);
            return;
        }

        isInjecting = true;
        injectForm(contentWrapper);

        // Re-inject if React destroys our form or un-hides native picker
        activeContentObserver = new MutationObserver(function () {
            if (isInjecting) return;

            var picker = contentWrapper.querySelector('.components-datetime');
            if (picker && !picker.classList.contains('pk-hidden')) {
                picker.classList.add('pk-hidden');
            }

            if (!contentWrapper.querySelector('.pk-gutenberg-jalali-form')) {
                isInjecting = true;
                injectForm(contentWrapper);
                isInjecting = false;
            }
        });

        activeContentObserver.observe(contentWrapper, { childList: true, subtree: true });
        isInjecting = false;
    }

    // ── Main MutationObserver — detect popover open/close ──

    var bodyObserver = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var mut = mutations[i];

            // Detect popover removal — disconnect content observer
            var removed = mut.removedNodes;
            for (var r = 0; r < removed.length; r++) {
                var rNode = removed[r];
                if (rNode.nodeType !== 1) continue;
                var wasDialog = (rNode.classList && rNode.classList.contains('editor-post-schedule__dialog'))
                    || (rNode.querySelector && rNode.querySelector('.editor-post-schedule__dialog'));
                if (wasDialog) {
                    popoverOpen = false;
                    if (activeContentObserver) {
                        activeContentObserver.disconnect();
                        activeContentObserver = null;
                    }
                }
            }

            // Detect popover addition
            var added = mut.addedNodes;
            for (var j = 0; j < added.length; j++) {
                var node = added[j];
                if (node.nodeType !== 1) continue;

                var dialog = node.classList && node.classList.contains('editor-post-schedule__dialog')
                    ? node
                    : node.querySelector && node.querySelector('.editor-post-schedule__dialog');

                if (dialog) {
                    popoverOpen = true;
                    // Delay to let React finish rendering popover content
                    (function (d) {
                        setTimeout(function () { handlePopoverOpen(d); }, 10);
                    })(dialog);
                }
            }
        }
    });

    bodyObserver.observe(document.body, { childList: true, subtree: true });

    // ── Store subscribe for popover form updates (e.g. "Now" button) ──

    wp.data.subscribe(function () {
        if (isDispatching || !popoverOpen) return;

        var dialog = document.querySelector('.editor-post-schedule__dialog');
        if (!dialog) return;

        var form = dialog.querySelector('.pk-gutenberg-jalali-form');
        if (form) {
            updateFormFromStore(form);
        }
    });

})(window.wp, window.PersianKitJalali);
