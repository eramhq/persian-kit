<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Settings\SettingStatus;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Parsi Date's settings next to Persian Kit's. 6.x keeps them in
 * wp_parsidate and wp_parsidate_{woocommerce,acf,edd,…}; 5.x kept them all
 * in wpp_settings, as 'enable', under older names.
 */
class ParsiDateSettings
{
    /** 5.x names of the core settings that 6.x renamed. */
    private const LEGACY_CORE = [
        'wpp_multilingual_support' => 'multilingual_support',
        'dis_input'                => 'hook_deactivator_list',
        'dev_mode'                 => 'debug_mode',
    ];

    private const LEGACY_WOOCOMMERCE = [
        'woo_per_price'           => 'fix_prices',
        'woo_accept_per_postcode' => 'fix_persian_postcode',
        'woo_accept_per_phone'    => 'fix_persian_phone',
        'woo_dropdown_cities'     => 'dropdown_cities',
        'woo_validate_postcode'   => 'validate_postcode',
        'woo_validate_phone'      => 'validate_phone',
    ];

    private const LEGACY_ACF = [
        'acf_fix_date'     => 'fix_date',
        'acf_persian_date' => 'save_persian_date',
    ];

    private const LEGACY_EDD = [
        'edd_prices'   => 'fix_prices',
        'edd_rial_fix' => 'fix_currency',
    ];

    /** Its bank gateways, by WooCommerce gateway id. */
    public const GATEWAYS = ['parsian', 'pasargad', 'mellat', 'melli'];

    /** @var array<string, array<string, mixed>> */
    private array $options;

    /**
     * @param array<string, array<string, mixed>>|null $options Section => settings; read from the database when null.
     */
    public function __construct(?array $options = null)
    {
        $this->options = $options ?? self::load();
    }

    /**
     * Its settings by section (core, woocommerce, acf, edd, elementor,
     * brizy, jet_engine), from 6.x options or 5.x's wpp_settings.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function load(): array
    {
        $core = get_option('wp_parsidate', []);
        if (is_array($core) && $core !== []) {
            $options = ['core' => $core];
            foreach (['woocommerce', 'acf', 'edd', 'elementor', 'brizy', 'jet_engine'] as $section) {
                $value = get_option('wp_parsidate_' . $section, []);
                $options[$section] = is_array($value) ? $value : [];
            }

            return $options;
        }

        $legacy = get_option('wpp_settings', []);

        return self::fromLegacy(is_array($legacy) ? $legacy : []);
    }

    /**
     * 5.x's single option as 6.x's sections, as 6.x's own upgrade does.
     *
     * @param array<string, mixed> $legacy
     * @return array<string, array<string, mixed>>
     */
    public static function fromLegacy(array $legacy): array
    {
        $core = [];
        $woo = [];
        $acf = [];
        $edd = [];

        foreach ($legacy as $key => $value) {
            if (isset(self::LEGACY_WOOCOMMERCE[$key])) {
                $woo[self::LEGACY_WOOCOMMERCE[$key]] = $value;
            } elseif (isset(self::LEGACY_ACF[$key])) {
                $acf[self::LEGACY_ACF[$key]] = $value;
            } elseif (isset(self::LEGACY_EDD[$key])) {
                $edd[self::LEGACY_EDD[$key]] = $value;
            } elseif ($key === 'woo_gateways' && is_array($value)) {
                foreach (array_keys($value) as $gateway) {
                    if (in_array($gateway, self::GATEWAYS, true)) {
                        $woo[$gateway . '_gateway_enable'] = true;
                    }
                }
            } else {
                $core[self::LEGACY_CORE[$key] ?? $key] = $value;
            }
        }

        return ['core' => $core, 'woocommerce' => $woo, 'acf' => $acf, 'edd' => $edd, 'elementor' => [], 'brizy' => [], 'jet_engine' => []];
    }

    public function on(string $section, string $key): bool
    {
        return AbstractSource::truthy($this->options[$section][$key] ?? false);
    }

    public function value(string $section, string $key): mixed
    {
        return $this->options[$section][$key] ?? null;
    }

    /**
     * Its bank gateways that are on.
     *
     * @return list<string>
     */
    public function gateways(): array
    {
        return array_values(array_filter(self::GATEWAYS, fn (string $gateway): bool => $this->on('woocommerce', $gateway . '_gateway_enable')));
    }

    /**
     * @return list<SettingRow>
     */
    public function rows(): array
    {
        $rows = [];
        // Parsi Date converts only for fa_IR; on a site in another language
        // and with one language its dates and digits never showed.
        $applied = ContentLanguage::isPersianLocale(get_locale()) || ContentLanguage::isMultilingual();
        $notApplied = __('Parsi Date did not apply on this site: it converts only on Persian sites.', 'persian-kit');

        if ($this->on('core', 'persian_date')) {
            $rows[] = new SettingRow(
                'persian_date',
                __('Jalali dates', 'persian-kit'),
                SettingStatus::Same,
                __('Jalali dates', 'persian-kit'),
                ['date_conversion.enabled' => true],
                $applied ? '' : $notApplied,
                $applied
            );
            $rows[] = new SettingRow(
                'persian_date_everywhere',
                __('Jalali dates in every date WordPress prints', 'persian-kit'),
                SettingStatus::Same,
                __('Convert every date (advanced)', 'persian-kit'),
                ['date_conversion.global_conversion' => true],
                __('Parsi Date converted date_i18n() and wp_date() everywhere, as this does.', 'persian-kit'),
                $applied
            );
        }

        if ($this->on('core', 'conv_permalinks')) {
            $multilingual = ContentLanguage::isMultilingual() && !$this->on('core', 'multilingual_support');
            $rows[] = new SettingRow(
                'conv_permalinks',
                __('Jalali dates in post links', 'persian-kit'),
                $multilingual ? SettingStatus::Close : SettingStatus::Same,
                __('Jalali dates in post links', 'persian-kit'),
                ['date_conversion.jalali_permalinks' => true],
                $multilingual
                    ? __('Persian Kit gives Jalali links to Persian posts only. The old Jalali links of posts in other languages redirect to their Gregorian links.', 'persian-kit')
                    : __('Every post keeps the link it has, byte for byte.', 'persian-kit')
            );
        }

        $contentKeys = ['conv_title', 'conv_page_title', 'conv_contents', 'conv_excerpt', 'conv_comments', 'conv_cats'];
        $contentOn = array_filter($contentKeys, fn (string $key): bool => $this->on('core', $key));
        if ($contentOn !== []) {
            $all = count($contentOn) === count($contentKeys);
            $rows[] = new SettingRow(
                'conv_content',
                __('Persian digits in titles, content, excerpts, comments and categories', 'persian-kit'),
                SettingStatus::Close,
                __('Persian digits', 'persian-kit'),
                ['digit_conversion.enabled' => true],
                $all
                    ? __('One switch covers them all.', 'persian-kit')
                    : __('One switch covers them all, so it also converts the places Parsi Date left alone.', 'persian-kit'),
                $all && $applied
            );
        }

        if ($this->on('core', 'conv_dates')) {
            $rows[] = new SettingRow('conv_dates', __('Persian digits in dates', 'persian-kit'), SettingStatus::Same, __('Persian digits in dates', 'persian-kit'), ['digit_conversion.dates' => true], '', $applied);
        }

        if ($this->on('core', 'conv_number_format_i18n') || $this->on('core', 'conv_comment_count')) {
            $rows[] = new SettingRow(
                'conv_numbers',
                __('Persian digits in numbers and comment counts', 'persian-kit'),
                SettingStatus::Close,
                __('Persian digits in counts and numbers', 'persian-kit'),
                ['digit_conversion.numbers' => true],
                __('Covers every number WordPress formats, comment counts included.', 'persian-kit'),
                $applied
            );
        }

        if ($this->on('core', 'conv_arabic')) {
            $rows[] = new SettingRow(
                'conv_arabic',
                __('Arabic ي and ك shown as Persian', 'persian-kit'),
                SettingStatus::Close,
                __('Persian ی and ک', 'persian-kit'),
                ['char_normalization.enabled' => true],
                __('Persian Kit fixes search and saving, not what is shown. To fix posts already saved, use "Fix letters in existing posts" below.', 'persian-kit')
            );
        }

        if ($this->on('core', 'enable_fonts')) {
            $rows[] = new SettingRow(
                'enable_fonts',
                __('Persian font in the admin', 'persian-kit'),
                SettingStatus::Close,
                __('Admin font: Vazirmatn', 'persian-kit'),
                ['admin_font.enabled' => true, 'admin_font.font' => 'vazirmatn'],
                __('Persian Kit\'s admin font is Vazirmatn, a similar typeface.', 'persian-kit')
            );
        }

        $rows = array_merge($rows, $this->woocommerceRows(), $this->acfRows(), $this->automaticRows(), $this->notYetRows());

        return $rows;
    }

    /**
     * @return list<SettingRow>
     */
    private function woocommerceRows(): array
    {
        $rows = [];

        if ($this->on('woocommerce', 'fix_prices')) {
            $rows[] = new SettingRow('fix_prices', __('WooCommerce: Persian digits in prices', 'persian-kit'), SettingStatus::Same, __('Persian digits in prices', 'persian-kit'), ['digit_conversion.prices' => true]);
        }

        if ($this->on('woocommerce', 'fix_persian_postcode') || $this->on('woocommerce', 'fix_persian_phone')) {
            $rows[] = new SettingRow('fix_persian_numbers', __('WooCommerce: accept Persian digits in postcode and phone', 'persian-kit'), SettingStatus::Same, __('Fix what customers type at checkout', 'persian-kit'), ['woocommerce.checkout_normalize' => true]);
        }

        $postcode = $this->on('woocommerce', 'validate_postcode');
        $phone = $this->on('woocommerce', 'validate_phone');
        if ($postcode || $phone) {
            $rows[] = new SettingRow(
                'validate_fields',
                __('WooCommerce: check postcode and phone', 'persian-kit'),
                $postcode && $phone ? SettingStatus::Same : SettingStatus::Close,
                __('Check what customers type at checkout', 'persian-kit'),
                ['woocommerce.checkout_validate' => true],
                $postcode && $phone ? '' : __('Persian Kit checks both, and the national ID when that field is on.', 'persian-kit'),
                $postcode && $phone
            );
        }

        if ($this->on('woocommerce', 'dropdown_cities')) {
            $rows[] = new SettingRow(
                'dropdown_cities',
                __('WooCommerce: cities as a list', 'persian-kit'),
                SettingStatus::Close,
                __('City suggestions', 'persian-kit'),
                ['woocommerce.city_select' => true],
                __('Customers get suggestions as they type, from the official list of each province, instead of a dropdown.', 'persian-kit')
            );
        }

        if ($this->on('woocommerce', 'fix_email_content_numbers')) {
            $rows[] = new SettingRow('fix_email_content_numbers', __('WooCommerce: Persian digits in emails', 'persian-kit'), SettingStatus::Same, __('Persian digits in WooCommerce emails', 'persian-kit'), ['digit_conversion.emails' => true]);
        }

        return $rows;
    }

    /**
     * @return list<SettingRow>
     */
    private function acfRows(): array
    {
        $rows = [];

        if ($this->on('acf', 'fix_date')) {
            $rows[] = new SettingRow('acf_fix_date', __('ACF: Jalali date fields', 'persian-kit'), SettingStatus::Same, __('ACF', 'persian-kit'), ['acf.enabled' => true]);
        }

        if ($this->on('acf', 'save_persian_date')) {
            $rows[] = new SettingRow(
                'acf_save_persian_date',
                __('ACF: save dates in Jalali', 'persian-kit'),
                SettingStatus::Automatic,
                '',
                [],
                __('Persian Kit saves Gregorian dates and shows them in Jalali. The ACF data task converts the dates saved in Jalali.', 'persian-kit')
            );
        }

        return $rows;
    }

    /**
     * @return list<SettingRow>
     */
    private function automaticRows(): array
    {
        $rows = [];
        $automatic = static fn (string $id, string $label, string $reason): SettingRow => new SettingRow($id, $label, SettingStatus::Automatic, '', [], $reason);

        if ($this->on('core', 'multilingual_support')) {
            $rows[] = $automatic('multilingual_support', __('Multilingual sites', 'persian-kit'), __('Persian Kit follows each page\'s language on WPML and Polylang sites.', 'persian-kit'));
        }
        if ($this->on('core', 'date_in_admin_bar')) {
            $rows[] = $automatic('date_in_admin_bar', __('Jalali date in the admin bar', 'persian-kit'), __('Dates in the admin are Jalali while Jalali dates are on.', 'persian-kit'));
        }
        if (in_array($this->value('core', 'months_name_type'), [null, '', 'persian'], true) && $this->on('core', 'persian_date')) {
            $rows[] = $automatic('months_name_type', __('Persian month names', 'persian-kit'), __('Persian Kit uses the Persian month names.', 'persian-kit'));
        }

        return $rows;
    }

    /**
     * Its settings with no match in Persian Kit, among those that are on.
     *
     * @return list<SettingRow>
     */
    private function notYetRows(): array
    {
        $rows = [];
        $notYet = static fn (string $id, string $label, string $reason = ''): SettingRow => new SettingRow($id, $label, SettingStatus::NotYet, '', [], $reason !== '' ? $reason : __('Persian Kit has no match yet.', 'persian-kit'));
        /* translators: %s: issue number, such as #14. */
        $planned = static fn (string $issue): string => sprintf(__('Planned for Persian Kit (%s).', 'persian-kit'), $issue);

        if ($this->on('core', 'dual_date')) {
            $rows[] = $notYet('dual_date', __('Dates in both calendars', 'persian-kit'), $planned('#14'));
        }
        if (in_array($this->value('core', 'months_name_type'), ['dari', 'kurdish', 'pashto'], true)) {
            $rows[] = $notYet('months_name_type', __('Dari, Kurdish or Pashto month names', 'persian-kit'), $planned('#33'));
        }
        if ($this->on('core', 'disable_widget_block')) {
            $rows[] = $notYet('disable_widget_block', __('Classic widgets screen', 'persian-kit'), __('Use the Classic Widgets plugin for this.', 'persian-kit'));
        }
        if (is_string($this->value('core', 'hook_deactivator_list')) && trim((string) $this->value('core', 'hook_deactivator_list')) !== '') {
            $rows[] = $notYet('hook_deactivator_list', __('Hooks turned off by name', 'persian-kit'));
        }
        if ($this->on('woocommerce', 'analytics_shamsi_date')) {
            $rows[] = $notYet('analytics_shamsi_date', __('WooCommerce: Jalali dates in Analytics', 'persian-kit'), $planned('#29'));
        }
        foreach ($this->gateways() as $gateway) {
            /* translators: %s: bank name, such as mellat. */
            $rows[] = $notYet('gateway_' . $gateway, sprintf(__('WooCommerce: %s bank gateway', 'persian-kit'), $gateway), __('Persian Kit has no payment gateways. See "Before you deactivate".', 'persian-kit'));
        }
        if (array_filter($this->options['edd'] ?? [], [AbstractSource::class, 'truthy']) !== []) {
            $rows[] = $notYet('edd', __('Easy Digital Downloads', 'persian-kit'), $planned('#35'));
        }
        foreach (['elementor' => 'Elementor', 'brizy' => 'Brizy', 'jet_engine' => 'JetEngine'] as $section => $name) {
            if (array_filter($this->options[$section] ?? [], [AbstractSource::class, 'truthy']) !== []) {
                $rows[] = $notYet($section, $name);
            }
        }

        return $rows;
    }
}
