<?php
/**
 * Character normalization module settings partial.
 *
 * @var array $moduleSettings Current settings for the char_normalization module.
 */

defined('ABSPATH') || exit;

$tehMarbuta = !empty($moduleSettings['teh_marbuta']);
?>
<div class="persian-kit-setting-row">
    <label>
        <input
            type="checkbox"
            name="modules[char_normalization][teh_marbuta]"
            value="1"
            <?php checked($tehMarbuta); ?>
        >
        <?php esc_html_e('Convert Arabic Teh Marbuta (ة) to Persian Heh (ه)', 'persian-kit'); ?>
    </label>
    <p class="description persian-kit-warning">
        <?php esc_html_e(
            'Warning: This may corrupt Arabic or Quranic text. Only enable if your content is exclusively Persian.',
            'persian-kit'
        ); ?>
    </p>
</div>

<hr class="persian-kit-setting-separator">

<div class="persian-kit-setting-row" x-data="persianKitNormalize()" x-init="init()">
    <h4 class="persian-kit-setting-row__title"><?php esc_html_e('Batch Normalization', 'persian-kit'); ?></h4>
    <p class="description" style="margin-bottom: 1em;">
        <?php esc_html_e('Normalize Arabic characters in existing posts. New posts are normalized automatically on save.', 'persian-kit'); ?>
    </p>

    <div class="persian-kit-batch-actions">
        <button
            type="button"
            class="button"
            @click="checkStatus()"
            :disabled="running"
        >
            <?php esc_html_e('Check Status', 'persian-kit'); ?>
        </button>

        <button
            type="button"
            class="button button-primary"
            @click="runNormalization()"
            :disabled="running"
            x-show="!done"
        >
            <span x-text="isResuming ? '<?php echo esc_js(__('Resume Normalization', 'persian-kit')); ?>' : '<?php echo esc_js(__('Run Normalization', 'persian-kit')); ?>'"></span>
        </button>

        <a
            href="#"
            @click.prevent="restart()"
            x-show="isResuming && !running"
            class="persian-kit-batch-restart"
        >
            <?php esc_html_e('Start Over', 'persian-kit'); ?>
        </a>
    </div>

    <!-- Status Table -->
    <template x-if="counts !== null">
        <table class="widefat fixed persian-kit-status-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post Type', 'persian-kit'); ?></th>
                    <th><?php esc_html_e('Affected', 'persian-kit'); ?></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="[type, count] in Object.entries(counts)" :key="type">
                    <tr>
                        <td x-text="type"></td>
                        <td x-text="count"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </template>

    <!-- Progress -->
    <p x-show="running" class="persian-kit-progress">
        <span class="spinner is-active"></span>
        <span x-text="progressText"></span>
    </p>

    <!-- Done -->
    <div x-show="done" class="notice notice-success inline">
        <p x-text="doneText"></p>
    </div>

    <!-- Error -->
    <div x-show="error" class="notice notice-error inline">
        <p x-text="error"></p>
    </div>
</div>

<script>
function persianKitNormalize() {
    return {
        nextBatchTimer: null,
        running: false,
        done: false,
        counts: null,
        isResuming: false,
        progressText: '',
        doneText: '',
        error: '',
        totalProcessed: 0,
        totalModified: 0,

        async fetchApi(endpoint, method = 'GET') {
            const response = await fetch(persianKitSettings.restUrl + endpoint, {
                method: method,
                headers: {
                    'X-WP-Nonce': persianKitSettings.nonce,
                    'Content-Type': 'application/json',
                },
            });
            if (!response.ok) throw new Error(response.statusText);
            return response.json();
        },

        applyStatus(data) {
            const job = data.job || { status: 'idle' };

            this.counts = data.counts ?? this.counts;
            this.isResuming = !!data.is_resuming;
            this.totalProcessed = job.processed || 0;
            this.totalModified = job.modified || 0;

            if (job.status === 'running') {
                this.running = true;
                this.done = false;
                this.progressText = `Processed ${this.totalProcessed} posts (${this.totalModified} modified)...`;
                return;
            }

            this.running = false;

            if (job.status === 'completed') {
                this.done = true;
                this.doneText = `Done! ${this.totalProcessed} posts processed, ${this.totalModified} modified.`;
                this.isResuming = false;
                return;
            }
        },

        async checkStatus(silent = false) {
            if (!silent) {
                this.error = '';
            }

            try {
                const data = await this.fetchApi('normalize/status');
                this.applyStatus(data);
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

        init() {
            this.checkStatus(true).then(() => {
                if (this.running) {
                    this.queueNextBatch();
                }
            });
        },
    };
}
</script>
