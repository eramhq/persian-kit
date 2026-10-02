<?php
/**
 * Tools tab: fixes the letters of posts that are already saved.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$postTypeLabels = [];
foreach (get_post_types(['public' => true], 'objects') as $postType => $postTypeObject) {
    $postTypeLabels[$postType] = $postTypeObject->labels->name;
}

// Media titles and captions are rarely typed in Persian by hand; opt in.
$selectedPostTypes = array_values(array_diff(array_keys($postTypeLabels), ['attachment']));
?>
<section
    class="persian-kit-tool"
    x-data="persianKitNormalize(<?php echo esc_attr(wp_json_encode(['labels' => $postTypeLabels, 'selected' => $selectedPostTypes])); ?>)"
>
    <div class="persian-kit-tool__header">
        <span class="persian-kit-tool__icon">
            <?php echo \PersianKit\Components\Icon::render('tools'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
        </span>
        <div>
            <h2 class="persian-kit-tool__title"><?php esc_html_e('Fix letters in existing posts', 'persian-kit'); ?></h2>
            <p class="description">
                <?php esc_html_e('Count first, then fix. Back up your database before fixing.', 'persian-kit'); ?>
            </p>
        </div>
    </div>

    <fieldset class="persian-kit-post-types" :disabled="busy || isResuming">
        <legend><?php esc_html_e('Post types', 'persian-kit'); ?></legend>
        <?php foreach ($postTypeLabels as $postType => $postTypeLabel) : ?>
            <label>
                <input type="checkbox" value="<?php echo esc_attr($postType); ?>" x-model="postTypes">
                <?php echo esc_html($postTypeLabel); ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <div class="persian-kit-batch-actions" x-show="!confirming">
        <button
            type="button"
            class="button"
            @click="preview()"
            :disabled="busy || postTypes.length === 0"
        >
            <?php esc_html_e('Count posts', 'persian-kit'); ?>
        </button>

        <button
            type="button"
            class="button button-primary"
            @click="confirming = true"
            :disabled="busy || settingsDirty || postTypes.length === 0"
        >
            <span x-show="isResuming"><?php esc_html_e('Resume fixing posts…', 'persian-kit'); ?></span>
            <span x-show="!isResuming"><?php esc_html_e('Fix posts…', 'persian-kit'); ?></span>
        </button>

        <a
            href="#"
            @click.prevent="restart()"
            x-show="isResuming && !running"
            class="persian-kit-batch-restart"
        >
            <?php esc_html_e('Start over', 'persian-kit'); ?>
        </a>
    </div>

    <p x-show="settingsDirty" class="description persian-kit-warning">
        <?php esc_html_e('Save your settings first. The fix uses the saved settings.', 'persian-kit'); ?>
    </p>

    <!-- Confirmation -->
    <div x-show="confirming" class="persian-kit-batch-confirm">
        <p>
            <strong><?php esc_html_e('This changes your posts, without revisions, and cannot be undone.', 'persian-kit'); ?></strong>
        </p>
        <p>
            <label>
                <input type="checkbox" x-model="backupConfirmed">
                <?php esc_html_e('I have a recent database backup', 'persian-kit'); ?>
            </label>
        </p>
        <p class="persian-kit-batch-actions">
            <button type="button" class="button button-primary" @click="confirmRun()" :disabled="!backupConfirmed">
                <?php esc_html_e('Fix posts now', 'persian-kit'); ?>
            </button>
            <button type="button" class="button" @click="cancelConfirm()">
                <?php esc_html_e('Cancel', 'persian-kit'); ?>
            </button>
        </p>
    </div>

    <!-- Preview counts -->
    <template x-if="counts !== null">
        <table class="persian-kit-status-table">
            <caption class="screen-reader-text"><?php esc_html_e('Posts that would change, by post type', 'persian-kit'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Post type', 'persian-kit'); ?></th>
                    <th scope="col"><?php esc_html_e('Posts to fix', 'persian-kit'); ?></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in countRows" :key="row.type">
                    <tr>
                        <td x-text="row.label"></td>
                        <td x-text="row.countText"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </template>

    <div class="persian-kit-batch-status" aria-live="polite">
        <!-- Progress -->
        <div x-show="busy" class="persian-kit-progress">
            <span x-text="progressText"></span>
            <?php // No total is known ahead, so the bar moves without a percentage. ?>
            <span class="persian-kit-progress__bar" aria-hidden="true"></span>
        </div>

        <!-- Preview result -->
        <p x-show="!busy && previewText !== ''" x-text="previewText"></p>

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
</section>
