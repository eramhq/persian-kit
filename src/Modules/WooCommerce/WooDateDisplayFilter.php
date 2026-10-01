<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\DateConversion\JalaliFormatter;

defined('ABSPATH') || exit;

/**
 * Converts date_i18n() output to Jalali while WooCommerce renders dates: on
 * WooCommerce admin screens, inside WooCommerce template parts (shop pages,
 * My Account, emails) and inside the order-confirmation blocks.
 *
 * The context is tracked with flags set by screen and render hooks, so the
 * date_i18n filter itself does no work outside those contexts.
 */
class WooDateDisplayFilter
{
    private const ORDER_CONFIRMATION_BLOCK_PREFIX = 'woocommerce/order-confirmation';

    private static bool $inFilter = false;

    private bool $wooScreen = false;
    private int $templateDepth = 0;
    private int $blockDepth = 0;

    public function register(): void
    {
        add_filter('date_i18n', [$this, 'filterDateI18n'], 10, 4);
        add_action('current_screen', [$this, 'detectScreen']);
        add_action('woocommerce_before_template_part', [$this, 'enterTemplate']);
        add_action('woocommerce_after_template_part', [$this, 'leaveTemplate']);
        add_filter('render_block_data', [$this, 'enterBlock']);
        add_filter('render_block', [$this, 'leaveBlock'], 10, 2);
    }

    public function filterDateI18n(string $date, string $format, int $timestamp, bool $gmt = false): string
    {
        if (self::$inFilter || !$this->isWooDateContext() || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        self::$inFilter = true;

        try {
            return JalaliFormatter::fromOffsetTimestamp($format, $timestamp, $gmt);
        } finally {
            self::$inFilter = false;
        }
    }

    public function isWooDateContext(): bool
    {
        return $this->wooScreen || $this->templateDepth > 0 || $this->blockDepth > 0;
    }

    /**
     * @param mixed $screen
     */
    public function detectScreen($screen): void
    {
        $this->wooScreen = $screen instanceof \WP_Screen && self::isWooScreen($screen);
    }

    public function enterTemplate(): void
    {
        $this->templateDepth++;
    }

    public function leaveTemplate(): void
    {
        $this->templateDepth = max(0, $this->templateDepth - 1);
    }

    /**
     * @param mixed $parsedBlock
     * @return mixed
     */
    public function enterBlock($parsedBlock)
    {
        if (self::isOrderConfirmationBlock($parsedBlock)) {
            $this->blockDepth++;
        }

        return $parsedBlock;
    }

    /**
     * @param mixed $content
     * @param mixed $parsedBlock
     * @return mixed
     */
    public function leaveBlock($content, $parsedBlock = null)
    {
        if (self::isOrderConfirmationBlock($parsedBlock)) {
            $this->blockDepth = max(0, $this->blockDepth - 1);
        }

        return $content;
    }

    private static function isWooScreen(\WP_Screen $screen): bool
    {
        if (str_starts_with($screen->id, 'woocommerce_page_') || str_starts_with($screen->id, 'edit-shop_')) {
            return true;
        }

        $orderTypes = function_exists('wc_get_order_types') ? wc_get_order_types() : ['shop_order'];

        return in_array($screen->post_type, $orderTypes, true) || in_array($screen->id, $orderTypes, true);
    }

    /**
     * @param mixed $parsedBlock
     */
    private static function isOrderConfirmationBlock($parsedBlock): bool
    {
        $name = is_array($parsedBlock) ? ($parsedBlock['blockName'] ?? null) : null;

        return is_string($name) && str_starts_with($name, self::ORDER_CONFIRMATION_BLOCK_PREFIX);
    }
}
