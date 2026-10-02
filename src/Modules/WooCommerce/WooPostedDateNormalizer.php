<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DateConversion\DateInputParser;

defined('ABSPATH') || exit;

/**
 * Converts Jalali dates typed into WooCommerce admin forms to Gregorian before
 * WooCommerce reads them from $_POST.
 *
 * The woocommerce_process_*_meta actions fire only after WooCommerce verified
 * woocommerce_meta_nonce. The variations AJAX handlers check their nonces
 * themselves, after our early callbacks, so those callbacks verify the same
 * nonces first.
 *
 * Values are written back slashed, as WordPress keeps $_POST, because
 * WooCommerce unslashes them again when it reads them.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Missing
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitizeScalar() unslashes and sanitizes every value read.
 */
class WooPostedDateNormalizer
{
    private const LOCALIZED_DATE_PATTERN = '[0-9۰-۹٠-٩]{4}-(?:[0۰٠][1-9۱-۹١-٩]|[1۱١][0-2۰-۲٠-٢])-(?:[0۰٠][1-9۱-۹١-٩]|[12۱۲١٢][0-9۰-۹٠-٩]|[3۳٣][01۰۱٠١])';

    public function register(): void
    {
        add_action('woocommerce_process_product_meta', [$this, 'normalizeProductDates'], 5);
        add_action('woocommerce_process_shop_coupon_meta', [$this, 'normalizeCouponDates'], 5);
        add_action('woocommerce_process_shop_order_meta', [$this, 'normalizeOrderDates'], 5);
        add_action('wp_ajax_woocommerce_save_variations', [$this, 'normalizeVariationDates'], 1);
        add_action('wp_ajax_woocommerce_bulk_edit_variations', [$this, 'normalizeBulkSaleSchedule'], 1);
        add_filter('woocommerce_date_input_html_pattern', [$this, 'filterDateInputHtmlPattern']);
    }

    public function normalizeProductDates(): void
    {
        $this->normalizeScalarDateField('_sale_price_dates_from');
        $this->normalizeScalarDateField('_sale_price_dates_to');
    }

    public function normalizeCouponDates(): void
    {
        $this->normalizeScalarDateField('expiry_date');
    }

    public function normalizeOrderDates(): void
    {
        $this->normalizeScalarDateField('order_date');
        $this->normalizeDigitsField('order_date_hour');
        $this->normalizeDigitsField('order_date_minute');
        $this->normalizeDigitsField('order_date_second');
        $this->normalizeArrayPostField('access_expires');
    }

    public function normalizeVariationDates(): void
    {
        if (!check_ajax_referer('save-variations', 'security', false) || !current_user_can('edit_products')) {
            return;
        }

        $this->normalizeArrayPostField('variable_sale_price_dates_from');
        $this->normalizeArrayPostField('variable_sale_price_dates_to');
    }

    /**
     * Variations > Bulk actions > Set scheduled sale dates sends the dates
     * typed into two prompts, which WooCommerce reads with strtotime().
     */
    public function normalizeBulkSaleSchedule(): void
    {
        if (!check_ajax_referer('bulk-edit-variations', 'security', false) || !current_user_can('edit_products')) {
            return;
        }

        if (!isset($_POST['bulk_action']) || $this->sanitizeScalar($_POST['bulk_action']) !== 'variable_sale_schedule') {
            return;
        }

        if (!isset($_POST['data']) || !is_array($_POST['data'])) {
            return;
        }

        foreach (['date_from', 'date_to'] as $key) {
            if (!isset($_POST['data'][$key])) {
                continue;
            }

            $value = $this->sanitizeScalar($_POST['data'][$key]);

            // WooCommerce's "no date": the prompt was cancelled.
            if ($value === 'false') {
                continue;
            }

            $_POST['data'][$key] = wp_slash(DateInputParser::normalize($value));
        }
    }

    public function filterDateInputHtmlPattern(string $pattern): string
    {
        return self::LOCALIZED_DATE_PATTERN;
    }

    private function normalizeArrayPostField(string $key): void
    {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return;
        }

        foreach ($_POST[$key] as $index => $value) {
            $_POST[$key][$index] = wp_slash(DateInputParser::normalize($this->sanitizeScalar($value)));
        }
    }

    private function normalizeScalarDateField(string $key): void
    {
        if (!isset($_POST[$key])) {
            return;
        }

        $_POST[$key] = wp_slash(DateInputParser::normalize($this->sanitizeScalar($_POST[$key])));
    }

    private function normalizeDigitsField(string $key): void
    {
        if (!isset($_POST[$key])) {
            return;
        }

        $_POST[$key] = wp_slash(DigitConverter::toEnglish($this->sanitizeScalar($_POST[$key])));
    }

    private function sanitizeScalar(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }
}
