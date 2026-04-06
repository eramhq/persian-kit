/**
 * Persian Kit — Admin Date Override
 *
 * Replaces Gregorian date inputs with Jalali equivalents in:
 *   1. Quick Edit (inline editing on post list tables)
 *   2. Classic Editor publish box ("Edit" timestamp)
 *
 * Strategy: Overlay Jalali inputs, hide original Gregorian inputs,
 * sync Jalali→Gregorian in real-time so WP core JS always reads valid values.
 *
 * Depends on: jQuery, PersianKitJalali (jalali.js)
 */
(function ($, Jalali) {
    'use strict';

    if (!$ || !Jalali) {
        return;
    }

    var g2j = Jalali.gregorianToJalali;
    var j2g = Jalali.jalaliToGregorian;
    var MONTHS = Jalali.JALALI_MONTHS;
    var monthLength = Jalali.jalaliMonthLength;
    var pad = Jalali.pad;

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Build a Jalali month <select> element.
     */
    function buildMonthSelect(selectedMonth, idPrefix) {
        var html = '<select id="' + idPrefix + '_jmm" name="' + idPrefix + '_jmm" class="pk-jalali-input">';
        for (var i = 1; i <= 12; i++) {
            html += '<option value="' + pad(i) + '"' +
                (i === selectedMonth ? ' selected' : '') +
                '>' + pad(i) + '-' + MONTHS[i] + '</option>';
        }
        html += '</select>';
        return html;
    }

    /**
     * Build the Jalali date overlay HTML.
     *
     * @param {number} jy  Jalali year
     * @param {number} jm  Jalali month (1-12)
     * @param {number} jd  Jalali day (1-31)
     * @param {string} hh  Hour (2-digit string)
     * @param {string} mn  Minute (2-digit string)
     * @param {string} idPrefix  Prefix for element IDs (e.g. "pk" or "pk_qe")
     */
    function buildJalaliUI(jy, jm, jd, hh, mn, idPrefix) {
        return '<div class="pk-jalali-date timestamp-wrap" dir="rtl">' +
            '<label><span class="screen-reader-text">ماه</span>' +
            buildMonthSelect(jm, idPrefix) +
            '</label>' +
            '<label><span class="screen-reader-text">روز</span>' +
            '<input type="text" id="' + idPrefix + '_jjj" name="' + idPrefix + '_jjj" value="' + pad(jd) + '" size="2" maxlength="2" autocomplete="off" class="pk-jalali-input" />' +
            '</label>, ' +
            '<label><span class="screen-reader-text">سال</span>' +
            '<input type="text" id="' + idPrefix + '_jaa" name="' + idPrefix + '_jaa" value="' + jy + '" size="4" maxlength="4" autocomplete="off" class="pk-jalali-input" />' +
            '</label> @ ' +
            '<label><span class="screen-reader-text">ساعت</span>' +
            '<input type="text" id="' + idPrefix + '_jhh" name="' + idPrefix + '_jhh" value="' + hh + '" size="2" maxlength="2" autocomplete="off" class="pk-jalali-input" />' +
            '</label> : ' +
            '<label><span class="screen-reader-text">دقیقه</span>' +
            '<input type="text" id="' + idPrefix + '_jmn" name="' + idPrefix + '_jmn" value="' + mn + '" size="2" maxlength="2" autocomplete="off" class="pk-jalali-input" />' +
            '</label>' +
            '</div>';
    }

    /**
     * Sync Jalali input values → hidden Gregorian inputs.
     *
     * @param {jQuery} $container  The container holding Jalali inputs
     * @param {string} idPrefix    Prefix for Jalali input IDs
     * @param {object} gregInputs  Object with jQuery refs to Gregorian inputs { $aa, $mm, $jj, $hh, $mn }
     */
    function bindSync($container, idPrefix, gregInputs) {
        $container.on('keyup change input', '#' + idPrefix + '_jaa, #' + idPrefix + '_jjj, #' + idPrefix + '_jmm', function () {
            var jy = parseInt($container.find('#' + idPrefix + '_jaa').val(), 10);
            var jm = parseInt($container.find('#' + idPrefix + '_jmm').val(), 10);
            var jd = parseInt($container.find('#' + idPrefix + '_jjj').val(), 10);

            if (!jy || !jm || !jd || jy < 1300 || jy > 1500 || jm < 1 || jm > 12 || jd < 1 || jd > 31) {
                return; // Don't sync invalid partial input
            }

            // Clamp day to month length
            var maxDay = monthLength(jm, jy);
            if (jd > maxDay) {
                jd = maxDay;
            }

            var greg = j2g(jy, jm, jd);
            gregInputs.$aa.val(greg[0]);
            gregInputs.$mm.val(pad(greg[1]));
            gregInputs.$jj.val(pad(greg[2]));
        });

        // Sync hour/minute directly
        $container.on('keyup change input', '#' + idPrefix + '_jhh', function () {
            gregInputs.$hh.val($(this).val());
        });
        $container.on('keyup change input', '#' + idPrefix + '_jmn', function () {
            gregInputs.$mn.val($(this).val());
        });
    }

    // ── Quick Edit Handler ───────────────────────────────────────────

    function initQuickEdit() {
        // Use event delegation on the post list table
        $('#the-list').on('click', '.editinline', function () {
            // Defer to let WP's inline-edit-post.js populate the fields first
            setTimeout(handleQuickEditOpen, 50);
        });
    }

    function handleQuickEditOpen() {
        var $editRow = $('tr.inline-edit-row:visible');
        if (!$editRow.length) return;

        var $dateWrap = $editRow.find('.inline-edit-date .timestamp-wrap').not('.pk-jalali-date');
        if (!$dateWrap.length) return;

        // Read Gregorian values from the inputs that WP core just populated
        var $aa = $editRow.find(':input[name="aa"]');
        var $mm = $editRow.find(':input[name="mm"]');
        var $jj = $editRow.find(':input[name="jj"]');
        var $hh = $editRow.find(':input[name="hh"]');
        var $mn = $editRow.find(':input[name="mn"]');

        var year = parseInt($aa.val(), 10);
        var month = parseInt($mm.val(), 10);
        var day = parseInt($jj.val(), 10);

        // Guard: if year is already Jalali range, we've already converted (re-click)
        if (year < 1700) return;

        var jalali = g2j(year, month, day);
        var hh = $hh.val() || '00';
        var mn = $mn.val() || '00';

        // Build and insert Jalali overlay
        var html = buildJalaliUI(jalali[0], jalali[1], jalali[2], hh, mn, 'pk_qe');
        $dateWrap.before(html);
        $dateWrap.hide();

        // Bind real-time sync
        var $container = $editRow.find('.inline-edit-date');
        bindSync($container, 'pk_qe', {
            $aa: $aa,
            $mm: $mm,
            $jj: $jj,
            $hh: $hh,
            $mn: $mn
        });
    }

    // Clean up Jalali overlay when Quick Edit is closed
    $(document).on('click', '.button-link.cancel, .inline-edit-save .save', function () {
        // Small delay to let WP process first
        setTimeout(function () {
            $('.pk-jalali-date').each(function () {
                var $wrap = $(this);
                $wrap.siblings('.timestamp-wrap').show();
                $wrap.remove();
            });
        }, 100);
    });

    // ── Classic Editor Handler ───────────────────────────────────────

    function initClassicEditor() {
        // The "Edit" link next to the publish date
        $(document).on('click', 'a.edit-timestamp, #timestamp-wrap a.edit-timestamp', function () {
            // Defer so WP's slide-down animation starts
            setTimeout(handleClassicEditorOpen, 50);
        });
    }

    function handleClassicEditorOpen() {
        var $timestampdiv = $('#timestampdiv');
        if (!$timestampdiv.length) return;

        // Don't add overlay twice
        if ($timestampdiv.find('.pk-jalali-date').length) return;

        var $aa = $('#aa');
        var $mm = $('#mm');
        var $jj = $('#jj');
        var $hh = $('#hh');
        var $mn = $('#mn');

        var year = parseInt($aa.val(), 10);
        var month = parseInt($mm.val(), 10);
        var day = parseInt($jj.val(), 10);

        if (!year || year < 1700) return;

        var jalali = g2j(year, month, day);
        var hh = $hh.val() || '00';
        var mn = $mn.val() || '00';

        var html = buildJalaliUI(jalali[0], jalali[1], jalali[2], hh, mn, 'pk_ce');
        var $origWrap = $timestampdiv.find('.timestamp-wrap').not('.pk-jalali-date');
        $origWrap.before(html);
        $origWrap.hide();

        // Bind real-time sync
        bindSync($timestampdiv, 'pk_ce', {
            $aa: $aa,
            $mm: $mm,
            $jj: $jj,
            $hh: $hh,
            $mn: $mn
        });
    }

    // Cancel: remove overlay, WP core restores original Gregorian values from hidden_* fields
    $(document).on('click', '.cancel-timestamp', function () {
        var $timestampdiv = $('#timestampdiv');
        $timestampdiv.find('.pk-jalali-date').remove();
        $timestampdiv.find('.timestamp-wrap').show();
    });

    // Save/OK: overlay is removed, update the #timestamp display with Jalali
    $(document).on('click', '.save-timestamp', function () {
        // Delay to let post.js updateText() run first
        setTimeout(updateTimestampDisplay, 100);
    });

    /**
     * After post.js updates #timestamp, override the display with Jalali text.
     */
    function updateTimestampDisplay() {
        var $timestampdiv = $('#timestampdiv');

        // Clean up overlay
        $timestampdiv.find('.pk-jalali-date').remove();
        $timestampdiv.find('.timestamp-wrap').show();

        // Read the saved Gregorian values from inputs
        var year = parseInt($('#aa').val(), 10);
        var month = parseInt($('#mm').val(), 10);
        var day = parseInt($('#jj').val(), 10);
        var hh = $('#hh').val();
        var mn = $('#mn').val();

        if (!year || year < 1700) return;

        var jalali = g2j(year, month, day);
        var $b = $('#timestamp b');
        if ($b.length) {
            $b.text(
                jalali[2] + ' ' + MONTHS[jalali[1]] + ' ' + jalali[0] + ' @ ' + hh + ':' + mn
            );
        }
    }

    // ── Initialization ───────────────────────────────────────────────

    $(function () {
        initQuickEdit();
        initClassicEditor();
    });

})(window.jQuery, window.PersianKitJalali);
