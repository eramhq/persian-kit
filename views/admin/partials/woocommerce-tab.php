<?php
/**
 * The WooCommerce tab: conflict advice, the module's card with links to
 * the sections, then a card for each section. The sections show only while
 * the module is on.
 *
 * @var array<string, mixed> $module The module's data from AdminPage::render().
 */

use PersianKit\Components\Icon;
use PersianKit\Components\View;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$module = $args['module'];
$isEnabled = !empty($module['settings']['enabled']);
?>
<div class="persian-kit-panel__cards" x-data="{ enabled: <?php echo $isEnabled ? 'true' : 'false'; ?> }">
    <?php foreach ($module['advice'] as $report) : ?>
        <?php // Once the module is switched off, the advice is followed. ?>
        <div class="persian-kit-advice" x-show="enabled">
            <span class="persian-kit-advice__icon">
                <?php Icon::print('alert'); ?>
            </span>
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: name of another plugin, 2: Persian Kit module name. */
                    __('%1$s is active and handles some of the same things. We recommend turning off %2$s here.', 'persian-kit'),
                    $report['name'],
                    $module['label']
                ));
                ?>
                <a href="#persian-kit-compatibility"><?php esc_html_e('Review recommended settings', 'persian-kit'); ?></a>
            </p>
        </div>
    <?php endforeach; ?>

    <?php
    View::load('admin/partials/module-toggle', [
        'moduleKey'         => $module['key'],
        'moduleLabel'       => $module['label'],
        'moduleIcon'        => $module['icon'],
        'moduleDescription' => $module['description'],
        'module'            => $module['instance'],
        'moduleSettings'    => $module['settings'],
        'ownScope'          => false,
        'settingsView'      => 'admin/partials/woocommerce-jump',
    ]);

    $sectionsView = $module['instance']->settingsView();
    if ($sectionsView !== null) {
        View::load($sectionsView, ['moduleSettings' => $module['settings']]);
    }
    ?>
</div>
