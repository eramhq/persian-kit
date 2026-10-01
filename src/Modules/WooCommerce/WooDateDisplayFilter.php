<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\DateConversion\JalaliFormatter;

defined('ABSPATH') || exit;

class WooDateDisplayFilter
{
    private static bool $inFilter = false;
    /** @var string[] */
    private array $templateContextMarkers = [
        '/woocommerce/templates/order/tracking.php',
        '/woocommerce/templates/myaccount/view-order.php',
        '/woocommerce/templates/order/order-downloads.php',
        '/woocommerce/templates/emails/email-downloads.php',
        '/woocommerce/templates/emails/plain/email-downloads.php',
        '/woocommerce/src/Blocks/BlockTypes/OrderConfirmation/Downloads.php',
    ];

    public function register(): void
    {
        add_filter('date_i18n', [$this, 'filterDateI18n'], 10, 4);
    }

    public function filterDateI18n(string $date, string $format, int $timestamp, bool $gmt = false): string
    {
        if (self::$inFilter || DateDisplayGuard::shouldBypass($format) || !$this->isWooDateContext()) {
            return $date;
        }

        self::$inFilter = true;

        try {
            return JalaliFormatter::fromOffsetTimestamp($format, $timestamp, $gmt);
        } finally {
            self::$inFilter = false;
        }
    }

    /**
     * @param array<int, array<string, mixed>>|null $trace
     */
    public function isWooDateContext(?array $trace = null): bool
    {
        $trace ??= $this->debugTrace();

        foreach ($trace as $frame) {
            if (($frame['class'] ?? null) !== 'WC_DateTime') {
                continue;
            }

            if (($frame['function'] ?? null) === 'date_i18n') {
                return true;
            }

            continue;
        }

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file)) {
                continue;
            }

            if ($this->isTemplateContextFile($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function debugTrace(): array
    {
        return debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 12);
    }

    private function isTemplateContextFile(string $file): bool
    {
        foreach ($this->templateContextMarkers as $marker) {
            if (str_contains(str_replace('\\', '/', $file), $marker)) {
                return true;
            }
        }

        return false;
    }
}
