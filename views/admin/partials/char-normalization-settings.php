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

<div class="persian-kit-setting-row" x-data="persianKitNormalize">
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
            <span x-show="isResuming"><?php esc_html_e('Resume Normalization', 'persian-kit'); ?></span>
            <span x-show="!isResuming"><?php esc_html_e('Run Normalization', 'persian-kit'); ?></span>
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

    <!-- Paused: a job was started earlier and has not finished -->
    <p x-show="paused && !running" class="description" x-text="progressText"></p>

    <!-- Done -->
    <div x-show="done" class="notice notice-success inline">
        <p x-text="doneText"></p>
    </div>

    <!-- Error -->
    <div x-show="error" class="notice notice-error inline">
        <p x-text="error"></p>
    </div>
</div>
