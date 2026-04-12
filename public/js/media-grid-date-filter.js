(function (window) {
    'use strict';

    var QUERY_VAR = 'persian_kit_jalali_month';
    var media = window.wp && window.wp.media;
    var settings = media && media.view ? media.view.settings : null;
    var gridSettings = window._wpMediaGridSettings;

    if (!media || !media.view || !media.view.DateFilter || !settings) {
        return;
    }

    syncGridQueryWithUrl();
    patchDateFilter();

    function syncGridQueryWithUrl() {
        var params;
        var value;

        if (!gridSettings || !gridSettings.queryVars || !window.URLSearchParams) {
            return;
        }

        params = new window.URLSearchParams(window.location.search);
        value = normalizeDigits(params.get(QUERY_VAR) || '');

        if (!/^\d{6}$/.test(value)) {
            return;
        }

        gridSettings.queryVars[QUERY_VAR] = value;
        delete gridSettings.queryVars.year;
        delete gridSettings.queryVars.monthnum;
    }

    function patchDateFilter() {
        var originalCreateFilters = media.view.DateFilter.prototype.createFilters;

        media.view.DateFilter.prototype.createFilters = function () {
            var months = settings.months || {};
            var hasJalaliMonths = _.some(months, function (value) {
                return value && value.jalaliYearMonth;
            });

            if (!hasJalaliMonths) {
                return originalCreateFilters.apply(this, arguments);
            }

            this.filters = {};

            _.each(months, function (value, index) {
                this.filters[index] = {
                    text: value.text,
                    props: {
                        year: null,
                        monthnum: null,
                        persian_kit_jalali_month: value.jalaliYearMonth || null
                    }
                };
            }, this);

            this.filters.all = {
                text: media.view.l10n.allDates,
                props: {
                    year: null,
                    monthnum: null,
                    persian_kit_jalali_month: null
                },
                priority: 10
            };
        };
    }

    function normalizeDigits(value) {
        var map = {
            '۰': '0',
            '۱': '1',
            '۲': '2',
            '۳': '3',
            '۴': '4',
            '۵': '5',
            '۶': '6',
            '۷': '7',
            '۸': '8',
            '۹': '9',
            '٠': '0',
            '١': '1',
            '٢': '2',
            '٣': '3',
            '٤': '4',
            '٥': '5',
            '٦': '6',
            '٧': '7',
            '٨': '8',
            '٩': '9'
        };

        return String(value || '').replace(/[۰-۹٠-٩]/g, function (match) {
            return map[match] || match;
        });
    }
}(window));
