<?php
/**
 * "Before you deactivate": theme code, payment gateways, shipping zones.
 * Shown in Review and again at the Deactivate step.
 */

defined('ABSPATH') || exit;
?>
<div class="persian-kit-switch__group" x-show="checklist.length > 0">
    <h3><?php esc_html_e('Before you deactivate', 'persian-kit'); ?></h3>
    <template x-for="item in checklist" :key="item.key">
        <div class="persian-kit-callout" :class="{ 'persian-kit-callout--risk': item.blocking }">
            <p><strong x-text="item.title"></strong></p>
            <p x-text="item.description"></p>
            <ul class="persian-kit-switch__entries" x-show="item.entries.length > 0">
                <template x-for="(entry, index) in item.entries" :key="index">
                    <li>
                        <template x-if="entry.url">
                            <a :href="entry.url" x-text="entry.label"></a>
                        </template>
                        <template x-if="!entry.url">
                            <code class="persian-kit-switch__code" x-text="entry.label"></code>
                        </template>
                        <span x-show="entry.detail" x-text="' ' + (entry.detail || '')"></span>
                    </li>
                </template>
            </ul>
            <template x-if="item.extra && item.extra.snippet">
                <div class="persian-kit-switch__snippet">
                    <p class="description" x-text="item.extra.snippet_help"></p>
                    <textarea readonly rows="8" class="large-text code" dir="ltr" x-text="item.extra.snippet" @focus="$event.target.select()" :aria-label="item.title"></textarea>
                    <p>
                        <button type="button" class="button" @click="copy(item.extra.snippet)"><?php esc_html_e('Copy', 'persian-kit'); ?></button>
                        <button type="button" class="button" @click="rescan()" :disabled="busy"><?php esc_html_e('Scan again', 'persian-kit'); ?></button>
                        <span x-show="copied" class="description"><?php esc_html_e('Copied.', 'persian-kit'); ?></span>
                    </p>
                </div>
            </template>
            <template x-if="item.acknowledge || item.blocking">
                <label class="persian-kit-switch__option">
                    <input type="checkbox" x-model="acknowledged[item.key]">
                    <span x-text="item.extra && item.extra.acknowledge_label ? item.extra.acknowledge_label : <?php echo esc_attr(wp_json_encode(__('I have read this and sorted it out', 'persian-kit'))); ?>"></span>
                </label>
            </template>
        </div>
    </template>
</div>
