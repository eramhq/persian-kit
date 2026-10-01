/**
 * Persian Kit — Gutenberg Jalali Date Editor
 *
 * Adds a Jalali (Shamsi) "Publish" row to the post sidebar, and the Jalali
 * date to the pre-publish and post-publish panels, through the editor's
 * plugin slots. Core's Gregorian "Publish" row is hidden by
 * gutenberg-jalali.css, and core's Gregorian "Publish:" panel in the
 * pre-publish checks by hideCoreSchedulePanel(). If either stops matching,
 * both show and both still work.
 *
 * Dates are the editor's site-local strings (YYYY-MM-DDTHH:MM:SS) and are
 * never parsed with Date, so the browser's timezone plays no part.
 *
 * Depends on: wp-plugins, wp-element, wp-components, wp-data, wp-date,
 * wp-i18n, wp-editor, wp-edit-post, PersianKitJalali
 */
(function (wp, Jalali) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wp.data || !wp.components || !wp.date || !Jalali) {
        return;
    }

    // WordPress 6.5 has the slots in wp.editPost only; 6.6+ moved them to wp.editor.
    function slot(name) {
        return (wp.editor && wp.editor[name]) || (wp.editPost && wp.editPost[name]);
    }

    var PluginPostStatusInfo = slot('PluginPostStatusInfo');
    var PluginPrePublishPanel = slot('PluginPrePublishPanel');
    var PluginPostPublishPanel = slot('PluginPostPublishPanel');

    if (!PluginPostStatusInfo) {
        return;
    }

    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var useMemo = wp.element.useMemo;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var Button = wp.components.Button;
    var Dropdown = wp.components.Dropdown;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var __ = wp.i18n.__;

    var MONTHS = Jalali.JALALI_MONTHS;

    // Core shows no schedule for these either (DESIGN_POST_TYPES).
    var DESIGN_POST_TYPES = ['wp_template', 'wp_template_part', 'wp_block', 'wp_navigation'];

    // Translated by JalaliScript.php; core's strings as fallback.
    var labels = window.persianKitDateLabels || {};
    function label(key, fallback) {
        return labels[key] || __(fallback);
    }

    // Typed values must be whole numbers in these ranges before they reach the
    // store; the day is then clamped to the month by fromJalaliParts().
    var RANGES = { jy: [1300, 1500], jm: [1, 12], jd: [1, 31], hh: [0, 23], mn: [0, 59] };

    function siteNowParts() {
        // wp.date.date() formats in the site timezone from wp.date.getSettings().
        return Jalali.toJalaliParts(wp.date.date('Y-m-d\\TH:i:s'));
    }

    function storeParts(info) {
        return (!info.isFloating && Jalali.toJalaliParts(info.date)) || siteNowParts();
    }

    function toFields(parts) {
        return {
            jy: String(parts.jy),
            jm: String(parts.jm),
            jd: String(parts.jd),
            hh: Jalali.pad(parts.hh),
            mn: Jalali.pad(parts.mn)
        };
    }

    /** Parts from typed fields, or null while any field is incomplete or out of range. */
    function fieldsToParts(fields) {
        var parts = {};
        for (var key in RANGES) {
            if (!/^\d+$/.test(fields[key])) return null;
            var n = parseInt(fields[key], 10);
            if (n < RANGES[key][0] || n > RANGES[key][1]) return null;
            parts[key] = n;
        }
        return parts;
    }

    function usePublishDate() {
        return useSelect(function (select) {
            var editor = select('core/editor');
            var post = editor.getCurrentPost();

            return {
                date: editor.getEditedPostAttribute('date'),
                isFloating: editor.isEditedPostDateFloating(),
                canPublish: !!(post && post._links && post._links['wp:action-publish']),
                postType: editor.getCurrentPostType()
            };
        }, []);
    }

    function dateLabel(info) {
        if (!info.date || info.isFloating) {
            return __('Immediately');
        }
        var parts = Jalali.toJalaliParts(info.date);
        return parts ? Jalali.formatJalaliLabel(parts) : info.date;
    }

    function shouldShow(info) {
        return info.canPublish && DESIGN_POST_TYPES.indexOf(info.postType) === -1;
    }

    function JalaliDateForm() {
        var info = usePublishDate();
        var editPost = useDispatch('core/editor').editPost;
        var state = useState(function () { return toFields(storeParts(info)); });
        var fields = state[0];
        var setFields = state[1];
        // The date this form last sent, to tell its own edits from other changes.
        var sent = useRef(info.date);

        useEffect(function () {
            if (info.date !== sent.current) {
                sent.current = info.date;
                setFields(toFields(storeParts(info)));
            }
        }, [info.date]);

        function send(parts) {
            var date = Jalali.fromJalaliParts(parts);
            if (date) {
                sent.current = date;
                editPost({ date: date });
            }
        }

        function change(key) {
            return function (value) {
                var next = Object.assign({}, fields);
                next[key] = value;

                var parts = fieldsToParts(next);
                if (parts) {
                    // A new month or year can shorten the month; show the clamped day.
                    if (key === 'jm' || key === 'jy') {
                        next.jd = String(Jalali.clampJalaliParts(parts).jd);
                    }
                    send(parts);
                }
                setFields(next);
            };
        }

        // Leaving the form shows what was stored: incomplete input reverts, and
        // a day past the month's end shows the clamped day.
        function resync(event) {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                setFields(toFields(storeParts(info)));
            }
        }

        function setNow() {
            var parts = siteNowParts();
            setFields(toFields(parts));
            send(parts);
        }

        function numberField(key, text, extraClass) {
            return el(TextControl, {
                __next40pxDefaultSize: true,
                __nextHasNoMarginBottom: true,
                className: 'persian-kit-jalali-date__field persian-kit-jalali-date__' + extraClass,
                label: text,
                type: 'number',
                min: RANGES[key][0],
                max: key === 'jd' ? Jalali.jalaliMonthLength(+fields.jm || 1, +fields.jy || 1300) : RANGES[key][1],
                value: fields[key],
                onChange: change(key)
            });
        }

        var monthOptions = [];
        for (var m = 1; m <= 12; m++) {
            monthOptions.push({ label: MONTHS[m], value: String(m) });
        }

        return el('div', { className: 'persian-kit-jalali-date__form', onBlur: resync },
            el('fieldset', { className: 'persian-kit-jalali-date__fieldset' },
                el('legend', { className: 'persian-kit-jalali-date__legend' }, __('Date')),
                el('div', { className: 'persian-kit-jalali-date__row' },
                    numberField('jd', label('day', 'Day'), 'day'),
                    el(SelectControl, {
                        __next40pxDefaultSize: true,
                        __nextHasNoMarginBottom: true,
                        className: 'persian-kit-jalali-date__field persian-kit-jalali-date__month',
                        label: label('month', 'Month'),
                        value: fields.jm,
                        options: monthOptions,
                        onChange: change('jm')
                    }),
                    numberField('jy', label('year', 'Year'), 'year')
                )
            ),
            el('fieldset', { className: 'persian-kit-jalali-date__fieldset' },
                el('legend', { className: 'persian-kit-jalali-date__legend' }, __('Time')),
                el('div', { className: 'persian-kit-jalali-date__row persian-kit-jalali-date__time' },
                    numberField('hh', label('hour', 'Hour'), 'hour'),
                    numberField('mn', label('minute', 'Minute'), 'minute')
                )
            ),
            el(Button, { variant: 'secondary', size: 'compact', onClick: setNow }, __('Now'))
        );
    }

    function JalaliDateRow() {
        var info = usePublishDate();
        // The label sits at the row's outer edge, so a popover beside it clears the sidebar.
        var anchorState = useState(null);
        // A new object each render would restart the popover's positioning.
        var popoverProps = useMemo(function () {
            return {
                anchor: anchorState[0],
                placement: wp.i18n.isRTL() ? 'right-start' : 'left-start',
                offset: 36,
                shift: true
            };
        }, [anchorState[0]]);

        if (!shouldShow(info)) {
            return null;
        }

        return el(PluginPostStatusInfo, { className: 'persian-kit-jalali-date' },
            el('div', { className: 'persian-kit-jalali-date__label', ref: anchorState[1] }, __('Publish')),
            el(Dropdown, {
                className: 'persian-kit-jalali-date__dropdown',
                contentClassName: 'persian-kit-jalali-date__dialog',
                focusOnMount: true,
                popoverProps: popoverProps,
                renderToggle: function (toggle) {
                    return el(Button, {
                        size: 'compact',
                        variant: 'tertiary',
                        className: 'persian-kit-jalali-date__toggle',
                        onClick: toggle.onToggle,
                        'aria-expanded': toggle.isOpen
                    }, dateLabel(info));
                },
                renderContent: function () {
                    return el(JalaliDateForm);
                }
            })
        );
    }

    /**
     * Hide core's Gregorian "Publish:" panel in the pre-publish checks. It has
     * no class of its own and its neighbours share its markup, so it is found
     * by its title, core's own translated string. If nothing matches, both
     * panels show and both work.
     */
    function hideCoreSchedulePanel() {
        var title = __('Publish:');
        var panels = document.querySelectorAll(
            '.editor-post-publish-panel__prepublish > .components-panel__body:not(.persian-kit-jalali-date__panel)'
        );

        for (var i = 0; i < panels.length; i++) {
            var heading = panels[i].querySelector('.components-panel__body-title');
            // React does not manage this element's style, so it keeps the change.
            if (heading && heading.textContent.trim().indexOf(title) === 0) {
                panels[i].style.display = 'none';
            }
        }
    }

    /**
     * The date in the pre-publish panel's title. The title renders whenever
     * the pre-publish checks open (the panel body only when expanded), so
     * this is where core's panel is hidden.
     */
    function PrePublishDateValue(props) {
        useEffect(hideCoreSchedulePanel);

        return el('span', { className: 'persian-kit-jalali-date__value' }, props.label);
    }

    function JalaliPrePublishPanel() {
        var info = usePublishDate();
        if (!PluginPrePublishPanel || !shouldShow(info)) {
            return null;
        }

        return el(PluginPrePublishPanel, {
            className: 'persian-kit-jalali-date__panel',
            initialOpen: false,
            title: [__('Publish:'), ' ', el(PrePublishDateValue, { key: 'date', label: dateLabel(info) })]
        }, el(JalaliDateForm));
    }

    function JalaliPostPublishPanel() {
        var info = usePublishDate();
        if (!PluginPostPublishPanel || DESIGN_POST_TYPES.indexOf(info.postType) !== -1) {
            return null;
        }

        return el(PluginPostPublishPanel, { className: 'persian-kit-jalali-date__panel', initialOpen: true },
            el('p', { className: 'persian-kit-jalali-date__summary' },
                __('Publish:'), ' ', el('strong', null, dateLabel(info))
            )
        );
    }

    wp.plugins.registerPlugin('persian-kit-jalali-date', {
        icon: null,
        render: function () {
            return el(wp.element.Fragment, null,
                el(JalaliDateRow),
                el(JalaliPrePublishPanel),
                el(JalaliPostPublishPanel)
            );
        }
    });

})(window.wp, window.PersianKitJalali);
