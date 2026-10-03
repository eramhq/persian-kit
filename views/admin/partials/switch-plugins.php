<?php
/**
 * Tools tab: switching from Parsi Date, Persian WooCommerce or its shipping
 * plugin. Review, Deactivate, Import and Report open inside the card.
 *
 * @var array<string, mixed> $args {sources, job, csvUrl}
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$config = [
    'sources' => $args['sources'] ?? [],
    'job'     => $args['job'] ?? null,
    'csvUrl'  => $args['csvUrl'] ?? '',
];
?>
<section
    id="persian-kit-switch"
    class="persian-kit-tool persian-kit-switch"
    x-data="persianKitImport(<?php echo esc_attr(wp_json_encode($config)); ?>)"
>
    <div class="persian-kit-tool__header">
        <span class="persian-kit-tool__icon">
            <?php echo \PersianKit\Components\Icon::render('switch'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
        </span>
        <div>
            <h2 class="persian-kit-tool__title"><?php esc_html_e('Switch from another plugin', 'persian-kit'); ?></h2>
            <p class="description">
                <?php esc_html_e('Bring the settings and data of a plugin you used before into Persian Kit, without broken links or addresses.', 'persian-kit'); ?>
            </p>
        </div>
    </div>

    <!-- Every source this site used -->
    <template x-if="current === null">
        <div>
            <p x-show="sources.length === 0" class="description">
                <?php esc_html_e('No other Persian plugin left settings or data on this site.', 'persian-kit'); ?>
            </p>
            <ul class="persian-kit-switch__sources" x-show="sources.length > 0">
                <template x-for="source in sources" :key="source.key">
                    <li class="persian-kit-switch__source">
                        <span class="persian-kit-switch__name" x-text="source.name"></span>
                        <span class="persian-kit-chip" :class="'persian-kit-chip--state-' + source.state" x-text="source.state_label"></span>
                        <button type="button" class="button" @click="open(source.key)" :disabled="loading" x-text="openLabel(source)"></button>
                    </li>
                </template>
            </ul>
        </div>
    </template>

    <!-- One source, step by step -->
    <template x-if="current !== null">
        <div>
            <div class="persian-kit-switch__heading">
                <strong x-text="current.name"></strong>
                <span class="persian-kit-chip" :class="'persian-kit-chip--state-' + current.state" x-text="current.state_label"></span>
                <span class="persian-kit-switch__spacer"></span>
                <a href="#" @click.prevent="close()" x-show="!running"><?php esc_html_e('All plugins', 'persian-kit'); ?></a>
            </div>

            <ol class="persian-kit-switch__steps">
                <template x-for="(item, index) in steps" :key="item.key">
                    <li :class="{ 'is-done': item.done, 'is-current': item.key === step }" :aria-current="item.key === step ? 'step' : null">
                        <b x-text="item.done ? '✓' : formatNumber(index + 1)"></b>
                        <span x-text="item.label"></span>
                    </li>
                </template>
            </ol>

            <div x-ref="stepBody" tabindex="-1" class="persian-kit-switch__body">
                <?php \PersianKit\Components\View::load('admin/partials/switch-review'); ?>
                <?php \PersianKit\Components\View::load('admin/partials/switch-steps'); ?>
            </div>
        </div>
    </template>

    <div class="persian-kit-switch__status" aria-live="polite">
        <p x-show="loading" class="description"><?php esc_html_e('Loading…', 'persian-kit'); ?></p>

        <div x-show="error" class="notice notice-error inline">
            <p x-text="error"></p>
            <template x-if="cliCommand">
                <p>
                    <?php esc_html_e('The REST API of this site did not answer, often because a security plugin blocks it. Run the switch from WP-CLI instead:', 'persian-kit'); ?>
                    <code class="persian-kit-switch__code" x-text="cliCommand"></code>
                </p>
            </template>
        </div>
    </div>
</section>
