/**
 * Alpine component for the batch normalization panel on the settings page.
 * Batches run only while this page drives them; a reload pauses the job.
 */
export default function normalizeJob() {
    const { __, sprintf } = window.wp.i18n;

    return {
        nextBatchTimer: null,
        running: false,
        paused: false,
        done: false,
        counts: null,
        isResuming: false,
        progressText: '',
        doneText: '',
        error: '',
        totalProcessed: 0,
        totalModified: 0,

        init() {
            this.checkStatus(true);
        },

        async fetchApi(endpoint, method = 'GET') {
            const settings = window.persianKitSettings;
            const response = await fetch(settings.restUrl + endpoint, {
                method,
                headers: {
                    'X-WP-Nonce': settings.nonce,
                    'Content-Type': 'application/json',
                },
            });
            if (!response.ok) {
                throw new Error(response.statusText);
            }
            return response.json();
        },

        applyStatus(data) {
            const job = data.job || { status: 'idle' };

            this.counts = data.counts ?? this.counts;
            this.isResuming = !!data.is_resuming;
            this.totalProcessed = job.processed || 0;
            this.totalModified = job.modified || 0;

            // A "running" job on the server only means it has not finished.
            this.paused = job.status === 'running';

            if (this.paused) {
                this.done = false;
                this.progressText = sprintf(
                    /* translators: 1: number of posts processed, 2: number of posts changed. */
                    __('Processed %1$d posts (%2$d modified)…', 'persian-kit'),
                    this.totalProcessed,
                    this.totalModified
                );
                return;
            }

            if (job.status === 'completed') {
                this.done = true;
                this.doneText = sprintf(
                    /* translators: 1: number of posts processed, 2: number of posts changed. */
                    __('Done! %1$d posts processed, %2$d modified.', 'persian-kit'),
                    this.totalProcessed,
                    this.totalModified
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

        async runNormalization() {
            this.clearNextBatch();
            this.done = false;
            this.error = '';
            this.running = true;

            try {
                const data = await this.fetchApi('normalize/run', 'POST');
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
