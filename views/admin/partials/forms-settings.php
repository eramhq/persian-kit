<?php
/**
 * Forms module settings partial.
 *
 * @var array $moduleSettings Current settings for the forms module.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$checkboxes = [
    'cf7' => [
        'label'       => __('Contact Form 7', 'persian-kit'),
        'description' => __('Date fields get a Jalali date picker and still send a Gregorian date, which emails show as a Jalali date while Date Conversion is on. Adds [mobile_ir], [national_id], [postcode_ir], [card_ir] and [iban_ir] fields that check what is typed, and turns Persian digits into English digits in date, phone and number fields. Write [date name gregorian] to keep a Gregorian date field.', 'persian-kit'),
    ],
];
?>
<?php foreach ($checkboxes as $settingKey => $checkbox) : ?>
    <div class="persian-kit-setting-row">
        <label>
            <input type="hidden" name="persian_kit_settings[forms][<?php echo esc_attr($settingKey); ?>]" value="0">
            <input
                type="checkbox"
                name="persian_kit_settings[forms][<?php echo esc_attr($settingKey); ?>]"
                value="1"
                <?php checked(!empty($moduleSettings[$settingKey])); ?>
            >
            <?php echo esc_html($checkbox['label']); ?>
        </label>
        <p class="description"><?php echo esc_html($checkbox['description']); ?></p>
    </div>
<?php endforeach; ?>
