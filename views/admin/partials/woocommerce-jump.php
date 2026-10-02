<?php
/**
 * Links to the WooCommerce tab's sections, in the module's card. Each
 * section can be linked to, such as ?tab=woocommerce#prices.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$sections = \PersianKit\Modules\WooCommerce\WooCommerceModule::sections();
?>
<nav class="persian-kit-jump" aria-label="<?php esc_attr_e('WooCommerce sections', 'persian-kit'); ?>">
    <?php foreach ($sections as $id => $section) : ?>
        <a href="#<?php echo esc_attr($id); ?>"><?php echo esc_html($section['title']); ?></a>
    <?php endforeach; ?>
</nav>
