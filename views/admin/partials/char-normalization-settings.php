<?php
/**
 * Character normalization module settings partial.
 *
 * @var array $moduleSettings Current settings for the char_normalization module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$normalizeOnSave = !empty($moduleSettings['normalize_on_save']);
$tehMarbuta = !empty($moduleSettings['teh_marbuta']);

$postTypeLabels = [];
foreach (get_post_types(['public' => true], 'objects') as $postType => $postTypeObject) {
    $postTypeLabels[$postType] = $postTypeObject->labels->name;
}

// Media titles and captions are rarely typed in Persian by hand; opt in.
$selectedPostTypes = array_values(array_diff(array_keys($postTypeLabels), ['attachment']));
?>
<p class="description">
    <?php esc_html_e('Search always matches both spellings while this module is on. It does not change any content.', 'persian-kit'); ?>
</p>

<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[char_normalization][normalize_on_save]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[char_normalization][normalize_on_save]"
            value="1"
            <?php checked($normalizeOnSave); ?>
        >
        <?php esc_html_e('Fix letters when posts are saved', 'persian-kit'); ?>
    </label>
    <p class="description">
        <?php esc_html_e('Replaces Arabic ي and ك with Persian ی and ک, and Arabic-Indic digits with Persian digits, in the title, excerpt and content of public posts each time they are saved. Code blocks and HTML tags are left alone.', 'persian-kit'); ?>
    </p>
</div>

<div class="persian-kit-setting-row">
    <label>
        <input type="hidden" name="persian_kit_settings[char_normalization][teh_marbuta]" value="0">
        <input
            type="checkbox"
            name="persian_kit_settings[char_normalization][teh_marbuta]"
            value="1"
            <?php checked($tehMarbuta); ?>
        >
        <?php esc_html_e('When fixing letters, also replace Arabic Teh Marbuta (ة) with Persian Heh (ه)', 'persian-kit'); ?>
    </label>
    <p class="description persian-kit-warning">
        <?php esc_html_e(
            'Warning: This may corrupt Arabic or Quranic text. Only enable if your content is exclusively Persian.',
            'persian-kit'
        ); ?>
    </p>
</div>

<hr class="persian-kit-setting-separator">

<div
    class="persian-kit-setting-row"
    x-data="persianKitNormalize(<?php echo esc_attr(wp_json_encode(['labels' => $postTypeLabels, 'selected' => $selectedPostTypes])); ?>)"
>
    <h4 class="persian-kit-setting-row__title"><?php esc_html_e('Fix existing posts', 'persian-kit'); ?></h4>
    <p class="description">
        <?php esc_html_e('Rewrites posts that are already saved, using the saved settings above. Count first to see how many posts would change.', 'persian-kit'); ?>
    </p>

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
            <?php esc_html_e('Count posts to fix', 'persian-kit'); ?>
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
            <?php esc_html_e('Start Over', 'persian-kit'); ?>
        </a>
    </div>

    <p x-show="settingsDirty" class="description persian-kit-warning">
        <?php esc_html_e('You have unsaved changes. Save your settings before fixing posts, because the fix uses the saved settings.', 'persian-kit'); ?>
    </p>

    <!-- Confirmation -->
    <div x-show="confirming" class="notice notice-warning inline persian-kit-batch-confirm">
        <p>
            <strong><?php esc_html_e('This changes your saved posts and cannot be undone.', 'persian-kit'); ?></strong>
            <?php esc_html_e('Posts are updated directly in the database, without revisions. Back up your database before you continue.', 'persian-kit'); ?>
        </p>
        <p>
            <label>
                <input type="checkbox" x-model="backupConfirmed">
                <?php esc_html_e('I have a recent backup of my database', 'persian-kit'); ?>
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
        <table class="widefat fixed persian-kit-status-table">
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
                        <td x-text="row.count"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </template>

    <div class="persian-kit-batch-status" aria-live="polite">
        <!-- Progress -->
        <p x-show="busy" class="persian-kit-progress">
            <span class="spinner is-active"></span>
            <span x-text="progressText"></span>
        </p>

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
</div>
