<?php
/**
 * Date conversion module settings partial.
 *
 * @var array $moduleSettings Current settings for the date_conversion module.
 */

use PersianKit\Core\SettingsManager;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DateConversion\CalendarNames;
use PersianKit\Modules\DateConversion\GregorianCompanion;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Modules\DateConversion\JalaliPeriod;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Service\Language\ContentLanguage;

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

// Month names: one choice for the site, or one per language on multilingual sites.
$setLabels = [
    'iranian' => __('Iranian', 'persian-kit'),
    'dari'    => __('Dari (Afghanistan)', 'persian-kit'),
    'pashto'  => __('Pashto', 'persian-kit'),
    'kurdish' => __('Kurdish (Sorani)', 'persian-kit'),
];
$todayParts = JalaliPeriod::fromGregorian($today);
$nameExamples = [];
foreach (CalendarNames::SETS as $set) {
    $nameExamples[$set] = $withDigits($todayParts['jd'] . ' ' . CalendarNames::for($set)['months'][$todayParts['jm']] . ' ' . $todayParts['jy']);
}
$siteNames = isset($moduleSettings['month_names']) && in_array($moduleSettings['month_names'], CalendarNames::CHOICES, true) ? $moduleSettings['month_names'] : 'auto';
$namesByLocale = is_array($moduleSettings['month_names_by_locale'] ?? null) ? $moduleSettings['month_names_by_locale'] : [];
$siteLanguages = ContentLanguage::languages();

// Each select: its field name, label, value, and what Automatic gives.
$nameSelects = [];
if ($siteLanguages === []) {
    $nameSelects[] = [
        'name'  => 'persian_kit_settings[date_conversion][month_names]',
        'id'    => 'persian-kit-date_conversion-month_names',
        'label' => _x('Month names', 'names of the Jalali months', 'persian-kit'),
        'value' => $siteNames,
        'auto'  => CalendarNames::setFor(get_locale()),
    ];
} else {
    foreach ($siteLanguages as $locale) {
        $language = class_exists('Locale') ? (string) \Locale::getDisplayName($locale, get_user_locale()) : '';
        $value = $namesByLocale[$locale] ?? 'auto';
        $nameSelects[] = [
            'name'  => 'persian_kit_settings[date_conversion][month_names_by_locale][' . $locale . ']',
            'id'    => 'persian-kit-date_conversion-month_names-' . $locale,
            /* translators: %s: a language, such as Pashto (Afghanistan). */
            'label' => sprintf(__('Month names in %s', 'persian-kit'), $language !== '' ? $language : $locale),
            'value' => in_array($value, CalendarNames::CHOICES, true) ? $value : 'auto',
            // Null: the language shows Gregorian dates unless a set is chosen.
            'auto'  => ContentLanguage::languageReadsJalali($locale) ? CalendarNames::setFor($locale) : null,
        ];
    }
}
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

    <?php foreach ($nameSelects as $select) : ?>
        <?php
        $selectExamples = $nameExamples + ['auto' => $select['auto'] === null ? '' : $nameExamples[$select['auto']]];
        $autoLabel = $select['auto'] === null
            ? __('Automatic: Gregorian dates', 'persian-kit')
            /* translators: %s: the month names a language uses, such as Dari (Afghanistan). */
            : sprintf(__('Automatic: %s', 'persian-kit'), $setLabels[$select['auto']]);
        ?>
        <li
            class="persian-kit-option persian-kit-option--select"
            x-data="<?php echo esc_attr(wp_json_encode(['names' => $select['value'], 'nameExamples' => $selectExamples])); ?>"
        >
            <label class="persian-kit-option__label" for="<?php echo esc_attr($select['id']); ?>">
                <?php echo esc_html($select['label']); ?>
            </label>
            <select
                id="<?php echo esc_attr($select['id']); ?>"
                name="<?php echo esc_attr($select['name']); ?>"
                aria-describedby="<?php echo esc_attr($select['id'] . '-help'); ?>"
                x-model="names"
            >
                <option value="auto" <?php selected($select['value'], 'auto'); ?>><?php echo esc_html($autoLabel); ?></option>
                <?php foreach ($setLabels as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($select['value'], $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="persian-kit-option__help" id="<?php echo esc_attr($select['id'] . '-help'); ?>" x-show="nameExamples[names]"<?php echo $selectExamples[$select['value']] === '' ? ' x-cloak' : ''; ?>>
                <?php esc_html_e('Example:', 'persian-kit'); ?>
                <bdi x-text="nameExamples[names]"><?php echo esc_html($selectExamples[$select['value']]); ?></bdi>
            </span>
        </li>
    <?php endforeach; ?>
    <?php // The other kind of choice is kept: per language while the site has one, the site's on a multilingual site. ?>
    <li hidden>
        <?php if ($siteLanguages === []) : ?>
            <?php foreach ($namesByLocale as $locale => $value) : ?>
                <input type="hidden" name="<?php echo esc_attr('persian_kit_settings[date_conversion][month_names_by_locale][' . $locale . ']'); ?>" value="<?php echo esc_attr((string) $value); ?>">
            <?php endforeach; ?>
        <?php else : ?>
            <input type="hidden" name="persian_kit_settings[date_conversion][month_names]" value="<?php echo esc_attr($siteNames); ?>">
        <?php endif; ?>
    </li>

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
