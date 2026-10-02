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

$options = [
    'cf7' => [
        'label' => __('Contact Form 7', 'persian-kit'),
        'help' => __('Date fields get a Jalali date picker and still send a Gregorian date, which emails show as a Jalali date while Date Conversion is on. Adds [mobile_ir], [national_id], [postcode_ir], [card_ir] and [iban_ir] fields that check what is typed, and turns Persian digits into English digits in date, phone and number fields. Write [date name gregorian] to keep a Gregorian date field.', 'persian-kit'),
    ],
    'acf' => [
        'label' => __('ACF date fields', 'persian-kit'),
        'help' => __('Date Picker and Date Time Picker fields get a Jalali date picker, on edit screens and in acf_form(). Values are stored as before. While Date Conversion is on, get_field() and the_field() return Jalali dates in the field\'s return format, except formats such as Ymd that code reads.', 'persian-kit'),
    ],
];
?>

<ul class="persian-kit-options">
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'forms',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>
</ul>
