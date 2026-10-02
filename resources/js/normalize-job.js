/**
 * Alpine component for the batch normalization panel on the settings page.
 * Batches run only while this page drives them; a reload pauses the job.
 *
 * @param {{labels?: Object<string, string>, selected?: string[]}} config
 *   Public post type labels by slug, and the slugs checked by default.
 */
export default function normalizeJob(config = {}) {
    const { __, _n, sprintf } = window.wp.i18n;
    // In the admin's language: 1,284 in English, ۱٬۲۸۴ in Persian.
    const formatNumber = (number) => Number(number).toLocaleString(document.documentElement.lang || undefined);

    return {
        labels: config.labels || {},
        postTypes: config.selected || [],
        nextBatchTimer: null,
        running: false,
        previewing: false,
        paused: false,
        done: false,
        confirming: false,
        backupConfirmed: false,
        settingsDirty: false,
        counts: null,
        isResuming: false,
        progressText: '',
        previewText: '',
        doneText: '',
        error: '',
        totalProcessed: 0,
        totalModified: 0,

        get busy() {
            return this.running || this.previewing;
        },

        get countRows() {
            return Object.entries(this.counts || {}).map(([type, count]) => ({
                type,
                label: this.labels[type] || type,
                count,
                countText: formatNumber(count),
            }));
        },

        init() {
            // The fix runs with the saved settings, so edits in the settings
            // form must be saved first. This panel sits outside that form.
            const form = document.getElementById('persian-kit-settings-form');
            if (form) {
                const markDirty = () => {
                    this.settingsDirty = true;
                    this.confirming = false;
                };
                form.addEventListener('change', markDirty);
                form.addEventListener('input', markDirty);
            }

            this.checkStatus(true);
        },

        async fetchApi(endpoint, method = 'GET', params = {}) {
            const settings = window.persianKitSettings;
            let url = settings.restUrl + endpoint;
            const options = {
                method,
                headers: {
                    'X-WP-Nonce': settings.nonce,
                },
            };

            if (method === 'GET') {
                const query = new URLSearchParams();
                for (const [key, value] of Object.entries(params)) {
                    if (Array.isArray(value)) {
                        value.forEach((item) => query.append(`${key}[]`, item));
                    } else {
                        query.append(key, value);
                    }
                }
                const queryString = query.toString();
                if (queryString !== '') {
                    // Plain permalinks put the route in ?rest_route=.
                    url += (url.includes('?') ? '&' : '?') + queryString;
                }
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(params);
            }

            const response = await fetch(url, options);
            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error((body && body.message) || response.statusText);
            }
            return response.json();
        },

        applyStatus(data) {
            const job = data.job || { status: 'idle' };

            this.isResuming = !!data.is_resuming;
            this.totalProcessed = job.processed || 0;
            this.totalModified = job.modified || 0;

            // A "running" job on the server only means it has not finished.
            this.paused = job.status === 'running';

            if (this.paused) {
                // A resumed job keeps the post types it started with.
                if (Array.isArray(job.post_types) && job.post_types.length > 0) {
                    this.postTypes = job.post_types;
                }
                this.done = false;
                this.progressText = sprintf(
                    /* translators: 1: number of posts checked, 2: number of posts fixed. */
                    __('%1$s posts checked, %2$s fixed…', 'persian-kit'),
                    formatNumber(this.totalProcessed),
                    formatNumber(this.totalModified)
                );
                return;
            }

            if (job.status === 'completed') {
                this.done = true;
                this.doneText = sprintf(
                    /* translators: 1: number of posts checked, 2: number of posts fixed. */
                    __('Done. %1$s posts checked, %2$s fixed.', 'persian-kit'),
                    formatNumber(this.totalProcessed),
                    formatNumber(this.totalModified)
                );
                this.isResuming = false;
            }
        },

        async checkStatus(silent = false) {
            if (!silent) {
                this.error = '';
            }

            try {
                this.applyStatus(await this.fetchApi('normalize/status'));
            } catch (e) {
                if (!silent) {
                    this.error = e.message;
                }
            }
        },

        /**
         * Dry run over every selected post: counts the posts the saved
         * settings would change, without changing anything.
         */
        async preview() {
            this.error = '';
            this.previewText = '';
            this.counts = null;
            this.previewing = true;

            const counts = Object.fromEntries(this.postTypes.map((type) => [type, 0]));
            let cursor = 0;
            let checked = 0;
            let hasMore = true;

            try {
                while (hasMore) {
                    this.progressText = sprintf(
                        /* translators: %s: number of posts checked so far. */
                        __('Checked %s posts…', 'persian-kit'),
                        formatNumber(checked)
                    );

                    const data = await this.fetchApi('normalize/preview', 'GET', {
                        post_types: this.postTypes,
                        cursor,
                    });

                    for (const [type, count] of Object.entries(data.counts || {})) {
                        counts[type] = (counts[type] || 0) + count;
                    }
                    checked += data.processed || 0;
                    cursor = data.last_id || cursor;
                    hasMore = !!data.has_more;
                }

                const total = Object.values(counts).reduce((sum, count) => sum + count, 0);
                this.counts = counts;
                this.previewText = total === 0
                    ? __('No posts need fixing.', 'persian-kit')
                    : sprintf(
                        /* translators: 1: number of posts that would change, 2: number of posts checked. */
                        _n(
                            '%1$s post of %2$s checked would change.',
                            '%1$s posts of %2$s checked would change.',
                            total,
                            'persian-kit'
                        ),
                        formatNumber(total),
                        formatNumber(checked)
                    );
            } catch (e) {
                this.error = e.message;
            } finally {
                this.previewing = false;
            }
        },

        confirmRun() {
            this.confirming = false;
            this.backupConfirmed = false;
            this.runNormalization();
        },

        cancelConfirm() {
            this.confirming = false;
            this.backupConfirmed = false;
        },

        async runNormalization() {
            this.clearNextBatch();
            this.done = false;
            this.error = '';
            this.running = true;

            try {
                const data = await this.fetchApi('normalize/run', 'POST', {
                    post_types: this.postTypes,
                });
                this.applyStatus(data);

                if (data.has_more) {
                    this.queueNextBatch();
                    return;
                }

                this.running = false;
            } catch (e) {
                this.error = e.message;
                this.running = false;
            }
        },

        async restart() {
            this.error = '';
            try {
                await this.fetchApi('normalize/restart', 'POST');
                this.clearNextBatch();
                this.running = false;
                this.paused = false;
                this.isResuming = false;
                this.done = false;
                this.counts = null;
                this.totalProcessed = 0;
                this.totalModified = 0;
                this.progressText = '';
                this.previewText = '';
                this.doneText = '';
            } catch (e) {
                this.error = e.message;
            }
        },

        queueNextBatch() {
            if (this.nextBatchTimer !== null) {
                return;
            }

            this.nextBatchTimer = setTimeout(() => {
                this.nextBatchTimer = null;
                this.runNormalization();
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
