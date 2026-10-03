<?php

namespace PersianKit\Service\Import\Sources\PersianWooCommerce;

use PersianKit\Modules\WooCommerce\CallForPrice;
use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\Iran\IranProvinces;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Settings\SettingStatus;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce's settings ('yes'/'no' values in PW_Options) next to
 * Persian Kit's.
 */
class PersianWooCommerceSettings
{
    private const IRANIAN_CURRENCIES = ['IRR', 'IRT', 'IRHR', 'IRHT'];

    /** Its "call for price" texts' default, without the <strong> around it. */
    private const CALL_FOR_PRICE_DEFAULT = 'تماس بگیرید';

    /**
     * @return list<SettingRow>
     */
    public function rows(): array
    {
        $rows = [];
        $on = static fn (string $key): bool => AbstractSource::truthy(PersianWooCommerceSource::value($key));

        if ($on('enable_jalali_datepicker')) {
            $rows[] = new SettingRow(
                'enable_jalali_datepicker',
                __('Jalali dates and date picker', 'persian-kit'),
                SettingStatus::Same,
                __('Jalali dates, also in the shop admin', 'persian-kit'),
                ['date_conversion.enabled' => true, 'woocommerce.dates_admin' => true, 'date_conversion.global_conversion' => true]
            );
        }

        if ($on('persian_price')) {
            $rows[] = new SettingRow('persian_price', __('Persian digits in prices', 'persian-kit'), SettingStatus::Same, __('Persian digits in prices', 'persian-kit'), ['digit_conversion.prices' => true]);
        }

        $states = $this->allowedStates();
        if ($states !== []) {
            $rows[] = new SettingRow(
                'allowed_states',
                /* translators: %s: number of provinces. */
                sprintf(_n('Sells to %s province', 'Sells to %s provinces', count($states), 'persian-kit'), number_format_i18n(count($states))),
                SettingStatus::Same,
                __('Provinces you ship to', 'persian-kit'),
                ['woocommerce.allowed_states' => $states]
            );
        }

        // The shipping plugin turns this off and has its own lists.
        if ($on('enable_iran_cities') && !(new PwsSource())->hasData()) {
            $rows[] = new SettingRow(
                'enable_iran_cities',
                __('Iranian cities', 'persian-kit'),
                SettingStatus::Close,
                __('City suggestions', 'persian-kit'),
                ['woocommerce.city_select' => true],
                __('Customers get suggestions as they type, from the official list of each province, instead of a dropdown.', 'persian-kit')
            );
        }

        if ($on('fix_postcode_persian_number') || $on('fix_phone_persian_number')) {
            $rows[] = new SettingRow('fix_persian_numbers', __('Accept Persian digits in postcode and phone', 'persian-kit'), SettingStatus::Same, __('Fix what customers type at checkout', 'persian-kit'), ['woocommerce.checkout_normalize' => true]);
        }

        if ($on('postcode_validation') || $on('phone_validation')) {
            $both = $on('postcode_validation') && $on('phone_validation');
            $rows[] = new SettingRow(
                'validation',
                __('Check postcode and phone', 'persian-kit'),
                $both ? SettingStatus::Same : SettingStatus::Close,
                __('Check what customers type at checkout', 'persian-kit'),
                ['woocommerce.checkout_validate' => true],
                $both ? '' : __('Persian Kit checks both, and the national ID when that field is on.', 'persian-kit'),
                $both
            );
        }

        // It also turns off order notes, and covers only the classic checkout.
        if ($on('remove_extra_field_physical')) {
            $rows[] = new SettingRow(
                'remove_extra_field_physical',
                __('Fewer address fields for virtual products', 'persian-kit'),
                SettingStatus::Close,
                __('Shorter checkout when nothing needs shipping', 'persian-kit'),
                ['woocommerce.short_checkout' => true],
                __('The country and order notes stay. Persian Kit also shortens the block checkout.', 'persian-kit')
            );
        }

        if ($on('enable_call_for_price')) {
            $rows = array_merge($rows, $this->callForPriceRows($on));
        }

        $rows = array_merge($rows, $this->fontRows(), $this->automaticRows(), $this->notYetRows($on));

        return $rows;
    }

    /**
     * The provinces it sells to, as WooCommerce codes; empty for all.
     *
     * @return list<string>
     */
    public function allowedStates(): array
    {
        if (PersianWooCommerceSource::value('allowed_states') === 'all') {
            return [];
        }

        $selected = PersianWooCommerceSource::value('specific_allowed_states');
        $codes = [];
        foreach (is_array($selected) ? $selected : [] as $value) {
            $code = is_string($value) ? IranProvinces::resolve($value) : null;
            if ($code !== null && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        sort($codes);

        return $codes;
    }

    /**
     * Its texts for the product page and archives. Its home page and
     * related products texts have no match of their own: Persian Kit has
     * one text for every list.
     *
     * @param callable(string): bool $on
     * @return list<SettingRow>
     */
    private function callForPriceRows(callable $on): array
    {
        $texts = [];
        $html = false;
        foreach (['product' => 'call_for_price_text', 'archive' => 'call_for_price_text_on_archive', 'home' => 'call_for_price_text_on_home', 'related' => 'call_for_price_text_on_related'] as $place => $key) {
            $raw = PersianWooCommerceSource::value($key);
            $raw = is_string($raw) ? trim($raw) : '';
            // Its default wraps the text in <strong>; any other tag is a link or markup.
            $plain = (string) preg_replace('#</?(strong|b)>#i', '', $raw);
            $html = $html || $plain !== wp_strip_all_tags($plain);
            $text = CallForPrice::sanitizeText($plain);
            // Its default is Persian Kit's too, and stays translatable while empty.
            $texts[$place] = $text === self::CALL_FOR_PRICE_DEFAULT ? '' : $text;
        }

        $changes = ['woocommerce.call_for_price' => true];
        if ($texts['product'] !== '') {
            $changes['woocommerce.call_for_price_text'] = $texts['product'];
        }
        if ($texts['archive'] !== '' && $texts['archive'] !== $texts['product']) {
            $changes['woocommerce.call_for_price_list_text'] = $texts['archive'];
        }

        $reasons = [];
        if ($texts['home'] !== $texts['archive'] || $texts['related'] !== $texts['archive']) {
            $reasons[] = __('Persian Kit has one text for every list, so its home page and related products texts become the archive text.', 'persian-kit');
        }
        if ($html) {
            $reasons[] = __('The texts are imported without links or formatting; a phone number or page to link to can be set in Persian Kit.', 'persian-kit');
        }

        $rows = [new SettingRow(
            'enable_call_for_price',
            __('"Call for price" for products without a price', 'persian-kit'),
            $reasons === [] ? SettingStatus::Same : SettingStatus::Close,
            __('Text instead of an empty price', 'persian-kit'),
            $changes,
            implode(' ', $reasons)
        )];

        if ($on('call_for_price_hide_sale_sign')) {
            $rows[] = new SettingRow(
                'call_for_price_hide_sale_sign',
                __('No sale badge without a price', 'persian-kit'),
                SettingStatus::Automatic,
                '',
                [],
                __('Persian Kit shows no sale badge on a product without a price.', 'persian-kit')
            );
        }

        return $rows;
    }

    /**
     * @return list<SettingRow>
     */
    private function fontRows(): array
    {
        $font = (string) PersianWooCommerceSource::value('admin_font_family');

        if (str_starts_with($font, 'vazirmatn')) {
            $fanum = $font === 'vazirmatn-fanum';

            return [new SettingRow(
                'admin_font_family',
                $fanum ? __('Admin font: Vazirmatn with Persian digits', 'persian-kit') : __('Admin font: Vazirmatn', 'persian-kit'),
                $fanum ? SettingStatus::Close : SettingStatus::Same,
                __('Admin font: Vazirmatn', 'persian-kit'),
                ['admin_font.enabled' => true, 'admin_font.font' => 'vazirmatn'],
                $fanum ? __('Digits in the admin stay English.', 'persian-kit') : ''
            )];
        }

        if (str_starts_with($font, 'iransans') || str_starts_with($font, 'yekanbakh')) {
            return [new SettingRow(
                'admin_font_family',
                str_starts_with($font, 'iransans') ? __('Admin font: IRANSans', 'persian-kit') : __('Admin font: Yekan Bakh', 'persian-kit'),
                SettingStatus::Close,
                __('Admin font: Vazirmatn', 'persian-kit'),
                ['admin_font.enabled' => true, 'admin_font.font' => 'vazirmatn'],
                __('That font is commercial and can not come with Persian Kit; Vazirmatn is the free one it has.', 'persian-kit'),
                false
            )];
        }

        return [];
    }

    /**
     * @return list<SettingRow>
     */
    private function automaticRows(): array
    {
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : (string) get_option('woocommerce_currency');
        if (!in_array($currency, self::IRANIAN_CURRENCIES, true)) {
            return [];
        }

        return [new SettingRow(
            'currency',
            /* translators: %s: currency code, such as IRT. */
            sprintf(__('Currency: %s', 'persian-kit'), $currency),
            SettingStatus::Automatic,
            '',
            [],
            __('Persian Kit has the rial, toman, thousand rial and thousand toman currencies, so prices stay as they are.', 'persian-kit')
        )];
    }

    /**
     * @param callable(string): bool $on
     * @return list<SettingRow>
     */
    private function notYetRows(callable $on): array
    {
        $rows = [];
        $notYet = static fn (string $id, string $label, string $reason = ''): SettingRow => new SettingRow($id, $label, SettingStatus::NotYet, '', [], $reason !== '' ? $reason : __('Persian Kit has no match yet.', 'persian-kit'));
        $options = get_option('PW_Options', []);
        $options = is_array($options) ? $options : [];

        if ((float) ($options['minimum_order_amount'] ?? 0) > 0) {
            $rows[] = $notYet('minimum_order_amount', __('Minimum order amount', 'persian-kit'));
        }
        if (isset($options['variable_price']) && $options['variable_price'] !== 'range') {
            $rows[] = $notYet('variable_price', __('How variable prices show', 'persian-kit'));
        }
        if (!empty($options['admin_login_template']) || !empty($options['admin_login_logo_url'])) {
            $rows[] = $notYet('admin_login', __('Login page template and logo', 'persian-kit'));
        }
        if (AbstractSource::truthy($options['super_admin_boost_dashboard'] ?? 'no') || AbstractSource::truthy($options['super_admin_boost_woo'] ?? 'no')) {
            $rows[] = $notYet('super_admin', __('Super admin speed-ups', 'persian-kit'));
        }

        $translations = $this->translationCount();
        if ($translations > 0) {
            $rows[] = $notYet(
                'translations',
                /* translators: %s: number of translated phrases. */
                sprintf(_n('%s replaced phrase', '%s replaced phrases', $translations, 'persian-kit'), number_format_i18n($translations)),
                __('Persian Kit does not replace phrases. Install the Persian language pack of WooCommerce under Dashboard > Updates.', 'persian-kit')
            );
        }

        $noGateways = __('Persian Kit has no payment gateways. See "Before you deactivate".', 'persian-kit');
        foreach (PersianWooCommerceSource::gatewayIds() as $gateway) {
            /* translators: %s: payment gateway id. */
            $label = sprintf(__('Payment gateway: %s', 'persian-kit'), $gateway);
            $rows[] = $notYet('gateway_' . $gateway, $label, $noGateways);
        }

        return $rows;
    }

    private function translationCount(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_ir';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $table));
    }
}
