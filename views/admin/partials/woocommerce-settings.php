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

$checkboxes = [
    'checkout_normalize' => [
        'label'       => __('Fix what customers type at checkout', 'persian-kit'),
        'description' => __('Turns Persian and Arabic digits in phone numbers and postcodes into English digits, removes spaces and dashes from postcodes, and replaces Arabic ي and ك with Persian ی and ک in names and addresses. Applies to the classic and block checkout and to addresses edited in My Account.', 'persian-kit'),
    ],
    'checkout_validate'  => [
        'label'       => __('Check Iranian phone numbers and postcodes', 'persian-kit'),
        'description' => __('For addresses in Iran, the phone must be a valid mobile or landline number and the postcode must have 10 valid digits. Addresses in other countries are not checked.', 'persian-kit'),
    ],
    'city_select'        => [
        'label'       => __('City dropdown for Iranian addresses', 'persian-kit'),
        'description' => __('Lists the cities of the chosen province in the classic checkout and in My Account. The block checkout keeps a text field for the city, because WooCommerce does not let plugins change it.', 'persian-kit'),
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
    <?php foreach ($checkboxes as $settingKey => $checkbox) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', [
            'moduleKey'  => 'woocommerce',
            'settingKey' => $settingKey,
            'label'      => $checkbox['label'],
            'help'       => $checkbox['description'],
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>

    <li class="persian-kit-option persian-kit-option--select">
        <label class="persian-kit-option__label" for="persian-kit-national-id">
            <?php esc_html_e('National ID field at checkout', 'persian-kit'); ?>
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
            <?php esc_html_e('Asks for the customer\'s national ID (کد ملی) and checks it. Required asks every customer, in any country. The ID is shown on the order screen and in order emails.', 'persian-kit'); ?>
        </span>
    </li>
</ul>
