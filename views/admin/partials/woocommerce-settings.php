<?php
/**
 * WooCommerce module settings partial.
 *
 * @var array $moduleSettings Current settings for the woocommerce module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$options = [
    'checkout_normalize' => [
        'label' => __('Fix what customers type', 'persian-kit'),
        'help'  => __('English digits in phone numbers and postcodes, Persian ی and ک in names.', 'persian-kit'),
    ],
    'checkout_validate'  => [
        'label' => __('Check phone numbers and postcodes', 'persian-kit'),
        'help'  => __('For addresses in Iran only.', 'persian-kit'),
    ],
    'city_select'        => [
        'label' => __('City list for Iran', 'persian-kit'),
        'help'  => __('Suggests the province\'s cities. Customers can still type a village.', 'persian-kit'),
    ],
];

$nationalIdModes = [
    \PersianKit\Modules\WooCommerce\NationalIdField::OFF      => __('Off', 'persian-kit'),
    \PersianKit\Modules\WooCommerce\NationalIdField::OPTIONAL => __('Optional', 'persian-kit'),
    \PersianKit\Modules\WooCommerce\NationalIdField::REQUIRED => __('Required', 'persian-kit'),
];

$nationalId = $moduleSettings['national_id'] ?? \PersianKit\Modules\WooCommerce\NationalIdField::OFF;
?>
<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'woocommerce',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>

    <li class="persian-kit-option persian-kit-option--select">
        <label class="persian-kit-option__label" for="persian-kit-national-id">
            <?php esc_html_e('National ID at checkout', 'persian-kit'); ?>
        </label>
        <select
            id="persian-kit-national-id"
            name="persian_kit_settings[woocommerce][national_id]"
            aria-describedby="persian-kit-national-id-help"
        >
            <?php foreach ($nationalIdModes as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($nationalId, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="persian-kit-option__help" id="persian-kit-national-id-help">
            <?php esc_html_e('Shown on the order and in emails.', 'persian-kit'); ?>
        </span>
    </li>
</ul>
