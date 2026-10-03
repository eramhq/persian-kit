import { restFetch } from './rest-client.js';

/** Steps in order. Deactivate is skipped once the source is inactive. */
const STEP_ORDER = ['review', 'deactivate', 'import', 'report'];

/** Errors that mean the REST API itself is unreachable, not a refusal. */
const UNREACHABLE = new Set(['network_error', 'invalid_json', 'rest_no_route', 'rest_cookie_invalid_nonce', 'rest_disabled']);

const TAB_KEY = 'persianKitImportTab';

/**
 * A random id for this tab, kept for the session, so the server can tell a
 * second tab working on the same switch from this one.
 */
function tabId() {
    try {
        let id = window.sessionStorage.getItem(TAB_KEY);
        if (!id) {
            id = Math.random().toString(36).slice(2, 12);
            window.sessionStorage.setItem(TAB_KEY, id);
        }
        return id;
    } catch (e) {
        return Math.random().toString(36).slice(2, 12);
    }
}

/**
 * Alpine component for "Switch from another plugin" on the Tools tab:
 * Review, Deactivate, Import and Report open inside the card. The import
 * runs in batches this page drives; the job is kept on the server, so a
 * reload or another visit resumes it.
 *
 * @param {{sources?: Object[], job?: Object|null, csvUrl?: string}} config
 */
export default function importJob(config = {}) {
    const { __, _n, sprintf } = window.wp.i18n;
    // In the admin's language: 1,284 in English, ۱٬۲۸۴ in Persian.
    const formatNumber = (number) => Number(number).toLocaleString(document.documentElement.lang || undefined);

    return {
        __,
        sprintf,
        formatNumber,
        sources: config.sources || [],
        job: config.job || null,
        csvBase: config.csvUrl || '',
        current: null,
        review: null,
        step: 'list',
        rows: {},
        tasks: {},
        acknowledged: {},
        options: { status_map: {}, district_line: true, keep_source: false, fix_double_dates: false },
        backup: false,
        loading: false,
        busy: false,
        running: false,
        error: '',
        cliCommand: '',
        copied: false,
        report: null,
        reportOutcome: '',
        reportPage: 1,
        confirmingUndo: false,
        undoResult: null,
        nextBatchTimer: null,
        tab: tabId(),

        init() {
            // A switch in progress opens where it was left; a finished one
            // waits in the list, under its Report button.
            if (this.job && this.job.source && this.job.step !== 'report') {
                this.open(this.job.source);
            }

            // Back from the Plugins screen in another tab: check again.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.step === 'deactivate' && this.current) {
                    this.refresh();
                }
            });
        },

        get steps() {
            const index = STEP_ORDER.indexOf(this.step);
            const labels = {
                review: __('Review', 'persian-kit'),
                deactivate: __('Deactivate', 'persian-kit'),
                import: __('Import', 'persian-kit'),
                report: __('Report', 'persian-kit'),
            };

            return STEP_ORDER.map((key, position) => ({
                key,
                label: labels[key],
                done: position < index || (key === 'deactivate' && this.current && !this.current.active && index > 0),
            }));
        },

        get checklist() {
            return this.review ? this.review.checklist : [];
        },

        get keepingSource() {
            return !!this.options.keep_source;
        },

        get canDeactivate() {
            if (!this.backup) {
                return false;
            }

            return this.checklist.every((item) => !(item.blocking || item.acknowledge) || this.acknowledged[item.key]);
        },

        get summaryChips() {
            if (!this.review) {
                return [];
            }

            const counts = {};
            this.review.rows.forEach((row) => {
                counts[row.status] = (counts[row.status] || 0) + 1;
            });
            const texts = {
                /* translators: %s: number of settings. */
                same: (n) => sprintf(_n('%s the same', '%s the same', n, 'persian-kit'), formatNumber(n)),
                /* translators: %s: number of settings. */
                close: (n) => sprintf(_n('%s close', '%s close', n, 'persian-kit'), formatNumber(n)),
                /* translators: %s: number of settings. */
                not_yet: (n) => sprintf(_n('%s not yet', '%s not yet', n, 'persian-kit'), formatNumber(n)),
                /* translators: %s: number of settings. */
                automatic: (n) => sprintf(_n('%s automatic', '%s automatic', n, 'persian-kit'), formatNumber(n)),
            };

            return Object.keys(texts)
                .filter((status) => counts[status])
                .map((status) => ({ status, text: texts[status](counts[status]) }));
        },

        get reviewIntro() {
            return this.current
                ? sprintf(
                    /* translators: %s: plugin name, such as Parsi Date. */
                    __('Before anything changes, this shows what Persian Kit does with the settings and data of %s. Nothing has changed yet.', 'persian-kit'),
                    this.current.name
                )
                : '';
        },

        get deactivateIntro() {
            return this.current
                ? sprintf(
                    /* translators: %s: plugin name. */
                    __('Persian Kit imports only once %s is inactive, so the two never convert dates and digits at the same time. WordPress does not let one plugin turn another off: deactivate it on the Plugins screen, then come back here.', 'persian-kit'),
                    this.current.name
                )
                : '';
        },

        get quietNote() {
            return this.current && this.current.key === 'persian-woocommerce-shipping'
                ? __('Switch at a quiet time. Until the import ends, shipping zones and addresses may not match, and checkout may offer no shipping.', 'persian-kit')
                : __('Switch at a quiet time: between deactivating and the end of the import, the site runs without either plugin\'s settings.', 'persian-kit');
        },

        get deactivateLabel() {
            return this.current
                ? sprintf(
                    /* translators: %s: plugin name. */
                    __('Deactivate %s', 'persian-kit'),
                    this.current.name
                )
                : '';
        },

        get inactiveText() {
            return this.current
                ? sprintf(
                    /* translators: %s: plugin name. */
                    __('%s is inactive. Its settings and data can be imported now.', 'persian-kit'),
                    this.current.name
                )
                : '';
        },

        get finishedText() {
            return this.current
                ? sprintf(
                    /* translators: %s: plugin name. */
                    __('The switch from %s is finished.', 'persian-kit'),
                    this.current.name
                )
                : '';
        },

        get progressText() {
            if (!this.job) {
                return '';
            }

            const { processed, total, percent } = this.job.progress;
            if (this.job.status === 'paused') {
                return sprintf(
                    /* translators: %s: percentage done. */
                    __('Paused at %s%%.', 'persian-kit'),
                    formatNumber(percent)
                );
            }

            return sprintf(
                /* translators: 1: items converted, 2: all items, 3: percentage. */
                __('%1$s of %2$s items (%3$s%%)', 'persian-kit'),
                formatNumber(processed),
                formatNumber(total),
                formatNumber(percent)
            );
        },

        get jobTasks() {
            if (!this.job || !this.review) {
                return [];
            }

            const labels = { settings: __('Settings', 'persian-kit') };
            this.review.tasks.forEach((task) => {
                labels[task.key] = task.label;
            });

            return Object.entries(this.job.tasks).map(([key, task]) => ({
                key,
                status: task.status,
                label: labels[key] || key,
                text: task.status === 'unavailable' || task.status === 'failed'
                    ? task.reason
                    : sprintf(
                        /* translators: 1: items done, 2: all items. */
                        __('%1$s of %2$s', 'persian-kit'),
                        formatNumber(Math.min(task.processed, task.total)),
                        formatNumber(task.total)
                    ),
            }));
        },

        get reportFilters() {
            const counts = (this.report && this.report.counts) || {};
            const filter = (outcome, chip, text) => ({ outcome, chip, text: `${text} (${formatNumber(counts[outcome] || 0)})` });

            return [
                filter('changed', 'same', __('Done', 'persian-kit')),
                filter('not_imported', 'not_yet', __('Not imported', 'persian-kit')),
                filter('attention', 'close', __('Needs attention', 'persian-kit')),
            ];
        },

        get pageText() {
            if (!this.report) {
                return '';
            }

            return sprintf(
                /* translators: 1: page number, 2: number of pages. */
                __('Page %1$s of %2$s', 'persian-kit'),
                formatNumber(this.reportPage),
                formatNumber(this.report.pages)
            );
        },

        get csvUrl() {
            return this.csvBase && this.current ? `${this.csvBase}&source=${encodeURIComponent(this.current.key)}` : '';
        },

        get undoWarning() {
            return this.review && this.review.undo_warning ? this.review.undo_warning : '';
        },

        get undoText() {
            if (!this.undoResult) {
                return '';
            }

            return sprintf(
                /* translators: 1: values put back, 2: values kept because they changed after the import. */
                __('Undone: %1$s put back, %2$s kept because they changed after the import.', 'persian-kit'),
                formatNumber(this.undoResult.restored),
                formatNumber(this.undoResult.kept)
            );
        },

        openLabel(source) {
            if (!source.job) {
                return __('Review', 'persian-kit');
            }

            return source.job.step === 'report' ? __('Report', 'persian-kit') : __('Continue', 'persian-kit');
        },

        countText(count) {
            return sprintf(
                /* translators: %s: number of items. */
                _n('%s item', '%s items', count, 'persian-kit'),
                formatNumber(count)
            );
        },

        rowCurrent(row) {
            if (row.no_change) {
                return __('Already set: no change', 'persian-kit');
            }

            return sprintf(
                /* translators: %s: Persian Kit's current value of a setting. */
                __('Now: %s', 'persian-kit'),
                row.current
            );
        },

        statusLabel(status) {
            return sprintf(
                /* translators: 1: order status, 2: number of orders. */
                __('%1$s (%2$s orders)', 'persian-kit'),
                status.label,
                formatNumber(status.count)
            );
        },

        async open(key) {
            this.error = '';
            this.cliCommand = '';
            this.loading = true;
            this.undoResult = null;
            this.confirmingUndo = false;

            try {
                this.applyReview(await restFetch(`import/${key}/review`));
                this.step = this.stepFor(this.job);
                if (this.step === 'report') {
                    await this.loadReport('', 1);
                }
                this.focusStep();
            } catch (e) {
                this.fail(e, key);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Review's data, and the choices: those saved in the job, or the
         * defaults Review ticks.
         */
        applyReview(review) {
            this.review = review;
            this.current = review.source;
            this.job = review.source.job || null;

            const saved = this.job;
            const rows = {};
            review.rows.forEach((row) => {
                rows[row.id] = saved ? saved.rows.includes(row.id) : !!row.ticked;
            });
            this.rows = rows;

            const tasks = {};
            review.tasks.forEach((task) => {
                tasks[task.key] = task.available && (saved ? saved.task_keys.includes(task.key) : true);
            });
            this.tasks = tasks;

            const options = { status_map: {}, district_line: true, keep_source: false, fix_double_dates: false };
            review.notes.forEach((note) => {
                if (note.choice) {
                    options.keep_source = !!note.choice.keep;
                }
            });
            review.tasks.forEach((task) => {
                if (task.options && task.options.type === 'status_map') {
                    task.options.statuses.forEach((status) => {
                        options.status_map[status.slug] = task.options.default;
                    });
                }
                if (task.options && task.options.type === 'district_line') {
                    options.district_line = !!task.options.default;
                }
            });
            this.options = saved ? { ...options, ...saved.options, status_map: { ...options.status_map, ...(saved.options.status_map || {}) } } : options;

            const acknowledged = {};
            (saved ? saved.acknowledged : []).forEach((key) => {
                acknowledged[key] = true;
            });
            this.acknowledged = acknowledged;
            this.backup = saved ? !!saved.backup : false;
        },

        stepFor(job) {
            if (!job) {
                return 'review';
            }
            if (job.step === 'deactivate') {
                return 'deactivate';
            }

            return job.step === 'import' ? 'import' : 'report';
        },

        goTo(step) {
            this.step = step;
            this.focusStep();
        },

        focusStep() {
            this.$nextTick(() => {
                if (this.$refs.stepBody) {
                    this.$refs.stepBody.focus({ preventScroll: false });
                }
            });
        },

        close() {
            this.clearNextBatch();
            this.current = null;
            this.review = null;
            this.report = null;
            this.step = 'list';
            this.refreshSources();
        },

        async refreshSources() {
            try {
                const data = await restFetch('import/sources');
                this.sources = data.sources;
                this.job = data.job;
            } catch (e) {
                this.fail(e);
            }
        },

        async refresh(rescan = false) {
            if (!this.current) {
                return;
            }

            try {
                const review = await restFetch(`import/${this.current.key}/review`, 'GET', rescan ? { rescan: 1 } : {});
                this.review = review;
                this.current = review.source;
                this.job = review.source.job || this.job;
            } catch (e) {
                this.fail(e, this.current.key);
            }
        },

        async rescan() {
            this.busy = true;
            await this.refresh(true);
            this.busy = false;
        },

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 2000);
            } catch (e) {
                this.copied = false;
            }
        },

        choices() {
            return {
                rows: Object.keys(this.rows).filter((id) => this.rows[id]),
                tasks: Object.keys(this.tasks).filter((key) => this.tasks[key]),
                options: this.options,
                backup: this.backup,
                acknowledged: Object.keys(this.acknowledged).filter((key) => this.acknowledged[key]),
            };
        },

        /**
         * Saves the choices into the job. The server answers 409 while the
         * source still has to be deactivated; the job is saved either way.
         *
         * @returns {Promise<boolean>} Whether the import can start.
         */
        async saveChoices() {
            try {
                const data = await restFetch(`import/${this.current.key}/start`, 'POST', this.choices());
                this.job = data.job;

                return true;
            } catch (e) {
                if (e.status === 409 && e.code === 'persian_kit_import_not_ready') {
                    this.job = e.data && e.data.job ? e.data.job : this.job;

                    return false;
                }
                throw e;
            }
        },

        async continueFromReview() {
            this.error = '';
            this.busy = true;

            try {
                const ready = await this.saveChoices();
                if (ready) {
                    this.goTo('import');
                    this.run();
                } else {
                    this.goTo('deactivate');
                }
            } catch (e) {
                this.fail(e, this.current.key);
            } finally {
                this.busy = false;
            }
        },

        async startImport() {
            this.error = '';
            this.busy = true;

            try {
                if (await this.saveChoices()) {
                    this.goTo('import');
                    this.run();
                } else {
                    await this.refresh();
                }
            } catch (e) {
                this.fail(e, this.current.key);
            } finally {
                this.busy = false;
            }
        },

        async run() {
            this.clearNextBatch();
            this.error = '';
            this.running = true;

            try {
                const data = await restFetch('import/run', 'POST', { tab: this.tab });
                this.job = data.job;

                if (this.job.step === 'report') {
                    this.running = false;
                    await this.refresh();
                    this.step = 'report';
                    await this.loadReport('', 1);
                    this.focusStep();
                    return;
                }

                if (this.job.status === 'paused') {
                    this.running = false;
                    return;
                }

                this.queueNextBatch();
            } catch (e) {
                this.running = false;
                if (e.data && e.data.job) {
                    this.job = e.data.job;
                }
                this.fail(e, this.current ? this.current.key : '');
            }
        },

        async pause() {
            this.clearNextBatch();
            this.running = false;

            try {
                const data = await restFetch('import/pause', 'POST');
                this.job = data.job;
            } catch (e) {
                this.fail(e);
            }
        },

        async cancel() {
            this.clearNextBatch();
            this.busy = true;

            try {
                await restFetch('import/restart', 'POST');
                this.job = null;
                await this.refresh();
                this.goTo('review');
            } catch (e) {
                this.fail(e);
            } finally {
                this.busy = false;
            }
        },

        async loadReport(outcome = '', page = 1) {
            if (!this.current) {
                return;
            }

            try {
                this.report = await restFetch(`import/${this.current.key}/report`, 'GET', { outcome, page });
                this.reportOutcome = outcome;
                this.reportPage = page;
            } catch (e) {
                this.fail(e, this.current.key);
            }
        },

        async undo() {
            this.busy = true;
            this.error = '';
            let cursor = 0;
            let runId = '';
            const total = { restored: 0, kept: 0 };

            try {
                for (;;) {
                    const result = await restFetch(`import/${this.current.key}/undo`, 'POST', { cursor, run_id: runId, tab: this.tab });
                    cursor = result.cursor;
                    runId = result.run_id;
                    total.restored += result.restored;
                    total.kept += result.kept;
                    if (result.done) {
                        break;
                    }
                }

                this.undoResult = total;
                this.confirmingUndo = false;
                await this.refresh();
                await this.loadReport('', 1);
            } catch (e) {
                this.fail(e, this.current.key);
            } finally {
                this.busy = false;
            }
        },

        async forget() {
            this.busy = true;

            try {
                await restFetch(`import/${this.current.key}/forget`, 'POST');
                await this.refresh();
                this.report = null;
            } catch (e) {
                this.fail(e, this.current.key);
            } finally {
                this.busy = false;
            }
        },

        /**
         * Shows the error. When the REST API itself is unreachable, as when a
         * security plugin blocks it, also the WP-CLI command that does the
         * same switch.
         */
        fail(error, key = '') {
            this.error = error.message;

            const unreachable = error.status === 0 || error.status >= 500 || UNREACHABLE.has(error.code)
                || (error.status === 401) || (error.status === 403 && !String(error.code).startsWith('persian_kit_'));
            this.cliCommand = unreachable ? `wp persian-kit import ${key || (this.current && this.current.key) || 'list'}` : '';
        },

        queueNextBatch() {
            if (this.nextBatchTimer !== null) {
                return;
            }

            this.nextBatchTimer = setTimeout(() => {
                this.nextBatchTimer = null;
                this.run();
            }, 300);
        },

        clearNextBatch() {
            if (this.nextBatchTimer === null) {
                return;
            }

            clearTimeout(this.nextBatchTimer);
            this.nextBatchTimer = null;
        },
    };
}
