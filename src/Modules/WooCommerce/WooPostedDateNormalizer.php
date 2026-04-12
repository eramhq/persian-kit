<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class WooPostedDateNormalizer
{
    private const LOCALIZED_DATE_PATTERN = '[0-9۰-۹٠-٩]{4}-(?:[0۰٠][1-9۱-۹١-٩]|[1۱١][0-2۰-۲٠-٢])-(?:[0۰٠][1-9۱-۹١-٩]|[12۱۲١٢][0-9۰-۹٠-٩]|[3۳٣][01۰۱٠١])';

    public function register(): void
    {
        add_action('woocommerce_process_product_meta', [$this, 'normalizeProductDates'], 5);
        add_action('woocommerce_process_shop_coupon_meta', [$this, 'normalizeCouponDates'], 5);
        add_action('woocommerce_process_shop_order_meta', [$this, 'normalizeOrderDates'], 5);
        add_action('wp_ajax_woocommerce_save_variations', [$this, 'normalizeVariationDates'], 1);
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
        $this->normalizeArrayPostField('variable_sale_price_dates_from');
        $this->normalizeArrayPostField('variable_sale_price_dates_to');
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
            $_POST[$key][$index] = WooDateHelper::normalizeDateInputForWooSave($this->sanitizeScalar($value));
        }
    }

    private function normalizeScalarDateField(string $key): void
    {
        if (!isset($_POST[$key])) {
            return;
        }

        $_POST[$key] = WooDateHelper::normalizeDateInputForWooSave($this->sanitizeScalar($_POST[$key]));
    }

    private function normalizeDigitsField(string $key): void
    {
        if (!isset($_POST[$key])) {
            return;
        }

        $_POST[$key] = DigitConverter::toEnglish($this->sanitizeScalar($_POST[$key]));
    }

    private function sanitizeScalar(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $value));
    }
}
