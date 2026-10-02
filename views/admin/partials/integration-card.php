<?php
/**
 * One integration's card on the Integrations tab.
 *
 * state is one of:
 *   available    the plugin is active: a toggle, and a warning while it is
 *                off if forms use its fields
 *   unavailable  the plugin is too old or needs an add-on: a disabled
 *                toggle and the reason
 *   inactive     the plugin is not active: a link to it on WordPress.org,
 *                or to its website when it is not there
 * Integrations in the compat category have no toggle: they are on
 * whenever their plugin is.
 *
 * @var array<string, mixed> $card A card from AdminPage::integrationCards().
 */

use PersianKit\Components\Icon;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$card = $args['card'];
$key = $card['key'];
$state = $card['state'];
$isEnabled = !empty($card['settings']['enabled']);
$automatic = $card['category'] === 'compat';
$hasToggle = !$automatic && $state !== 'inactive';
$forms = $state === 'available' ? $card['forms'] : [];

$nameId = 'persian-kit-module-' . $key . '-name';
$descId = 'persian-kit-module-' . $key . '-description';
$reasonId = 'persian-kit-module-' . $key . '-reason';
$warningId = 'persian-kit-module-' . $key . '-warning';
$adviceId = 'persian-kit-module-' . $key . '-advice';

$describedBy = trim(implode(' ', [
    $card['description'] !== '' ? $descId : '',
    $state === 'unavailable' ? $reasonId : '',
    $card['advice'] !== [] ? $adviceId : '',
]));

$classes = ['persian-kit-integration'];
if ($state === 'available' && ($isEnabled || $automatic)) {
    $classes[] = 'is-on';
}
if ($state !== 'available') {
    $classes[] = 'is-' . $state;
}

$newTab = __('(opens in a new tab)', 'persian-kit');
?>
<li
    class="<?php echo esc_attr(implode(' ', $classes)); ?>"
    <?php if ($state === 'available' && !$automatic) : ?>
        x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }"
        :class="{ 'is-on': enabled }"
    <?php endif; ?>
>
    <?php foreach ($card['advice'] as $index => $report) : ?>
        <p
            class="persian-kit-integration__advice"
            <?php echo $index === 0 ? 'id="' . esc_attr($adviceId) . '"' : ''; ?>
            <?php // Once the integration is switched off, the advice is followed. ?>
            <?php echo $state === 'available' && !$automatic ? 'x-show="enabled"' : ''; ?>
        >
            <?php echo Icon::render('alert'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
            <span>
                <?php echo esc_html($report['summary']); ?>
                <a href="#persian-kit-compatibility"><?php esc_html_e('Review recommended settings', 'persian-kit'); ?></a>
            </span>
        </p>
    <?php endforeach; ?>

    <div class="persian-kit-integration__head">
        <span class="persian-kit-module__icon">
            <?php echo $card['icon'] !== '' ? Icon::render($card['icon']) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
        </span>

        <div class="persian-kit-integration__title">
            <span class="persian-kit-module__name" id="<?php echo esc_attr($nameId); ?>"><?php echo esc_html($card['label']); ?></span>
            <?php if ($card['new']) : ?>
                <span class="persian-kit-badge"><?php esc_html_e('New', 'persian-kit'); ?></span>
            <?php endif; ?>
            <?php if ($state === 'inactive') : ?>
                <span class="persian-kit-pill<?php echo $card['kept'] ? ' is-kept' : ''; ?>">
                    <?php
                    echo esc_html($card['kept']
                        ? __('Your settings are kept', 'persian-kit')
                        : __('Not active on this site', 'persian-kit'));
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($automatic && $state === 'available') : ?>
            <span class="persian-kit-module__status">
                <?php echo Icon::render('check'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
                <?php esc_html_e('Active automatically', 'persian-kit'); ?>
            </span>
        <?php elseif ($hasToggle) : ?>
            <label class="persian-kit-module__toggle">
                <?php if ($state === 'available') : ?>
                    <?php // Sent when the toggle is off; the checkbox overrides it when on. ?>
                    <input type="hidden" name="persian_kit_settings[<?php echo esc_attr($key); ?>][enabled]" value="0">
                    <input
                        type="checkbox"
                        name="persian_kit_settings[<?php echo esc_attr($key); ?>][enabled]"
                        value="1"
                        aria-labelledby="<?php echo esc_attr($nameId); ?>"
                        <?php if ($forms !== []) : ?>
                            :aria-describedby="enabled ? '<?php echo esc_attr($describedBy); ?>' : '<?php echo esc_attr(trim($describedBy . ' ' . $warningId)); ?>'"
                        <?php endif; ?>
                        <?php if ($describedBy !== '') : ?>
                            aria-describedby="<?php echo esc_attr($isEnabled || $forms === [] ? $describedBy : trim($describedBy . ' ' . $warningId)); ?>"
                        <?php endif; ?>
                        x-model="enabled"
                        <?php checked($isEnabled); ?>
                    >
                <?php else : ?>
                    <?php // Not sent: the stored setting is kept until the plugin can be used. ?>
                    <input
                        type="checkbox"
                        disabled
                        aria-labelledby="<?php echo esc_attr($nameId); ?>"
                        aria-describedby="<?php echo esc_attr($describedBy); ?>"
                    >
                <?php endif; ?>
                <span class="persian-kit-module__toggle-track"></span>
            </label>
        <?php endif; ?>
    </div>

    <?php if ($card['description'] !== '') : ?>
        <p class="persian-kit-integration__description" id="<?php echo esc_attr($descId); ?>"><?php echo esc_html($card['description']); ?></p>
    <?php endif; ?>

    <?php if ($forms !== []) : ?>
        <?php
        // Persian digits in a Persian admin, as the Tools tab shows its counts.
        $formatCount = static function (int $count): string {
            $number = number_format_i18n($count);

            return str_starts_with(get_user_locale(), 'fa') ? persian_kit_to_persian_digits($number) : $number;
        };
        $shown = array_slice($forms, 0, 3);
        $names = array_map(static fn (array $form): string => sprintf(
            '<a href="%s">%s</a>',
            esc_url($form['url']),
            esc_html($form['title'] !== '' ? $form['title'] : __('(no title)', 'persian-kit'))
        ), $shown);
        if (count($forms) > count($shown)) {
            /* translators: %s: number of other forms. */
            $names[] = esc_html(sprintf(_n('%s other', '%s others', count($forms) - count($shown), 'persian-kit'), $formatCount(count($forms) - count($shown))));
        }
        ?>
        <div class="persian-kit-module__warning" id="<?php echo esc_attr($warningId); ?>" x-show="!enabled"<?php echo $isEnabled ? ' x-cloak' : ''; ?>>
            <?php echo Icon::render('alert'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
            <p>
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: 1: number of forms, 2: their names, such as "Contact, Order and Signup". */
                        esc_html(_n(
                            '%1$s form uses Persian Kit fields: %2$s. While this is off, those fields accept any text.',
                            '%1$s forms use Persian Kit fields: %2$s. While this is off, those fields accept any text.',
                            count($forms),
                            'persian-kit'
                        )),
                        esc_html($formatCount(count($forms))),
                        wp_sprintf('%l', $names)
                    ),
                    ['a' => ['href' => true]]
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($state === 'unavailable') : ?>
        <p class="persian-kit-integration__reason" id="<?php echo esc_attr($reasonId); ?>"><?php echo esc_html($card['reason']); ?></p>
    <?php endif; ?>

    <?php if ($state === 'inactive' && $card['pluginUrl'] !== '') : ?>
        <a class="persian-kit-integration__link" href="<?php echo esc_url($card['pluginUrl']); ?>" target="_blank" rel="noopener noreferrer">
            <?php
            if (str_starts_with($card['pluginUrl'], 'https://wordpress.org/')) {
                esc_html_e('Plugin page on WordPress.org', 'persian-kit');
            } else {
                esc_html_e('Plugin website', 'persian-kit');
            }
            ?>
            <span class="screen-reader-text"><?php echo esc_html($newTab); ?></span>
        </a>
    <?php endif; ?>
</li>
