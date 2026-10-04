<?php
/**
 * Date conversion module settings partial.
 *
 * @var array $moduleSettings Current settings for the date_conversion module.
 */

use PersianKit\Core\SettingsManager;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DateConversion\GregorianCompanion;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Modules\DigitConversion\DigitConversionModule;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$moduleSettings = $args['moduleSettings'] ?? [];

$options = [
    'jalali_archives'   => [
        'label' => __('Jalali archives and calendar', 'persian-kit'),
        'help'  => __('Archive lists, the calendar and pages such as /1405/07/.', 'persian-kit'),
    ],
    'jalali_permalinks' => [
        'label' => __('Jalali dates in post links', 'persian-kit'),
        'help'  => __('/1405/07/09/my-post/. Old links redirect.', 'persian-kit'),
    ],
];

$gregorianOn = !empty($moduleSettings['gregorian_date']);
$gregorianChoices = [
    'gregorian_style'     => [
        'label'   => __('Gregorian date', 'persian-kit'),
        'model'   => 'style',
        'choices' => [
            'numeric' => __('Numbers', 'persian-kit'),
            'named'   => __('Month names', 'persian-kit'),
        ],
    ],
    'gregorian_order'     => [
        'label'   => _x('Order', 'which date comes first', 'persian-kit'),
        'model'   => 'order',
        'choices' => [
            'jalali_first'    => __('Jalali first', 'persian-kit'),
            'gregorian_first' => __('Gregorian first', 'persian-kit'),
        ],
    ],
    'gregorian_separator' => [
        'label'   => __('Between the dates', 'persian-kit'),
        'model'   => 'separator',
        'choices' => [
            'parentheses' => __('Parentheses ( )', 'persian-kit'),
            'slash'       => __('Slash /', 'persian-kit'),
            'dash'        => __('Dash –', 'persian-kit'),
        ],
    ],
];
$gregorianValues = [];
foreach ($gregorianChoices as $settingKey => $select) {
    $value = $moduleSettings[$settingKey] ?? '';
    $gregorianValues[$select['model']] = isset($select['choices'][$value]) ? $value : array_key_first($select['choices']);
}

// The example shows today's date with the digits the site shows in dates.
$storedSettings = get_option(SettingsManager::OPTION_KEY, []);
$digitSettings = array_replace(
    DigitConversionModule::defaults(),
    is_array($storedSettings) && is_array($storedSettings['digit_conversion'] ?? null) ? $storedSettings['digit_conversion'] : []
);
$withDigits = static fn (string $text): string => !empty($digitSettings['enabled']) && !empty($digitSettings['dates'])
    ? DigitConverter::toPersian($text)
    : $text;

$today = JalaliFormatter::dateTime();
$jalaliToday = $withDigits(JalaliFormatter::formatDateTime('j F Y', $today));
$examples = [];
foreach (GregorianCompanion::STYLES as $style) {
    $gregorianToday = $style === 'named'
        ? $withDigits(GregorianCompanion::gregorian('j F Y', $today))
        : GregorianCompanion::isolate($withDigits(GregorianCompanion::gregorian('Y-m-d', $today)));
    foreach (GregorianCompanion::SEPARATORS as $separator) {
        $examples[$style . ' jalali_first ' . $separator] = GregorianCompanion::join($jalaliToday, $gregorianToday, $separator);
        $examples[$style . ' gregorian_first ' . $separator] = GregorianCompanion::join($gregorianToday, $jalaliToday, $separator);
    }
}
$exampleKey = implode(' ', [$gregorianValues['style'], $gregorianValues['order'], $gregorianValues['separator']]);
?>
<ul
    class="persian-kit-options"
    x-data="<?php echo esc_attr(wp_json_encode(['gregorian' => $gregorianOn] + $gregorianValues + ['examples' => $examples])); ?>"
>
    <?php foreach ($options as $settingKey => $option) : ?>
        <?php
        \PersianKit\Components\View::load('admin/partials/checkbox-option', $option + [
            'moduleKey'  => 'date_conversion',
            'settingKey' => $settingKey,
            'checked'    => !empty($moduleSettings[$settingKey]),
        ]);
        ?>
    <?php endforeach; ?>

    <?php
    \PersianKit\Components\View::load('admin/partials/checkbox-option', [
        'moduleKey'  => 'date_conversion',
        'settingKey' => 'gregorian_date',
        'label'      => __('Show the Gregorian date too', 'persian-kit'),
        'help'       => __('Next to post and comment dates on your site. Feeds, search engines and admin screens keep one date.', 'persian-kit'),
        'checked'    => $gregorianOn,
        'model'      => 'gregorian',
    ]);
    ?>

    <?php // Shown while the box is ticked; their values are kept while it isn't. ?>
    <?php foreach ($gregorianChoices as $settingKey => $select) : ?>
        <?php $selectId = 'persian-kit-date_conversion-' . $settingKey; ?>
        <li class="persian-kit-option persian-kit-option--select persian-kit-option--nested" x-show="gregorian"<?php echo $gregorianOn ? '' : ' x-cloak'; ?>>
            <label class="persian-kit-option__label" for="<?php echo esc_attr($selectId); ?>">
                <?php echo esc_html($select['label']); ?>
            </label>
            <select
                id="<?php echo esc_attr($selectId); ?>"
                name="persian_kit_settings[date_conversion][<?php echo esc_attr($settingKey); ?>]"
                x-model="<?php echo esc_attr($select['model']); ?>"
            >
                <?php foreach ($select['choices'] as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($gregorianValues[$select['model']], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </li>
    <?php endforeach; ?>
    <li class="persian-kit-option persian-kit-option--nested" x-show="gregorian"<?php echo $gregorianOn ? '' : ' x-cloak'; ?>>
        <span class="persian-kit-option__help">
            <?php esc_html_e('Example:', 'persian-kit'); ?>
            <bdi x-text="examples[[style, order, separator].join(' ')]"><?php echo esc_html($examples[$exampleKey]); ?></bdi>
        </span>
    </li>

    <?php
    \PersianKit\Components\View::load('admin/partials/checkbox-option', [
        'moduleKey'  => 'date_conversion',
        'settingKey' => 'global_conversion',
        'label'      => __('Convert every date (advanced)', 'persian-kit'),
        'help'       => __('Only if your theme still shows Gregorian dates. It can confuse SEO, booking and form plugins.', 'persian-kit'),
        'warning'    => true,
        'checked'    => !empty($moduleSettings['global_conversion']),
    ]);
    ?>
</ul>
