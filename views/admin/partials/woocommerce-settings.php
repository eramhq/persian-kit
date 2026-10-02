<?php
/**
 * The WooCommerce tab's section cards, shown while the module is on. The
 * "enabled" state comes from the tab around them.
 *
 * @var array $moduleSettings Current settings for the woocommerce module.
 */

use PersianKit\Components\View;
use PersianKit\Core\AdminPage;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PersianKit\Modules\WooCommerce\WooCommerceModule;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];
$sections = WooCommerceModule::sections();

$checkoutOptions = [
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
    NationalIdField::OFF      => __('Off', 'persian-kit'),
    NationalIdField::OPTIONAL => __('Optional', 'persian-kit'),
    NationalIdField::REQUIRED => __('Required', 'persian-kit'),
];

$nationalId = $moduleSettings['national_id'] ?? NationalIdField::OFF;

/**
 * Opens a section card with its heading and description. $descriptionHtml,
 * when given, is already escaped and replaces the plain description.
 */
$sectionStart = static function (string $id, string $descriptionHtml = '') use ($sections): void {
    $description = $descriptionHtml !== '' ? $descriptionHtml : esc_html($sections[$id]['description']);
    ?>
    <section class="persian-kit-section" id="<?php echo esc_attr($id); ?>" aria-labelledby="persian-kit-section-<?php echo esc_attr($id); ?>" x-show="enabled">
        <div class="persian-kit-section__header">
            <h2 class="persian-kit-section__title" id="persian-kit-section-<?php echo esc_attr($id); ?>"><?php echo esc_html($sections[$id]['title']); ?></h2>
            <?php if ($description !== '') : ?>
                <p class="persian-kit-section__description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></p>
            <?php endif; ?>
        </div>
    <?php
};
?>

<?php $sectionStart('checkout'); ?>
    <div class="persian-kit-section__body">
        <ul class="persian-kit-options">
            <?php foreach ($checkoutOptions as $settingKey => $option) : ?>
                <?php
                View::load('admin/partials/checkbox-option', $option + [
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
    </div>
</section>

<?php
// Prices: a pointer to the setting on the Display tab, until this section has options of its own.
$sectionStart('prices', wp_kses(
    sprintf(
        /* translators: %s: link to the setting, such as "Display › Persian digits › Shop prices". */
        esc_html__('Persian digits in prices are set under %s.', 'persian-kit'),
        sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . AdminPage::MENU_SLUG . '&tab=display')),
            esc_html(implode(' › ', [
                __('Display', 'persian-kit'),
                __('Persian digits', 'persian-kit'),
                __('Shop prices', 'persian-kit'),
            ]))
        )
    ),
    ['a' => ['href' => true]]
));
?>
</section>

<?php $sectionStart('dates'); ?>
    <div class="persian-kit-section__body">
        <ul class="persian-kit-options">
            <?php
            View::load('admin/partials/checkbox-option', [
                'moduleKey'  => 'woocommerce',
                'settingKey' => 'dates_admin',
                'label'      => __('Jalali date picker in the shop admin', 'persian-kit'),
                'help'       => __('Order, product and coupon dates, and the month filter on the orders list.', 'persian-kit'),
                'checked'    => !empty($moduleSettings['dates_admin']),
            ]);
            ?>
        </ul>
    </div>
</section>
