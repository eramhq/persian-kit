<?php

namespace PersianKit\Modules\WooCommerce;

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
        if (self::$inFilter || $this->shouldBypassDisplayConversion($format) || !$this->isWooDateContext()) {
            return $date;
        }

        self::$inFilter = true;

        try {
            $timezone = $gmt ? new \DateTimeZone('UTC') : null;

            return JalaliFormatter::format($format, $timestamp, $timezone);
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

    private function shouldBypassDisplayConversion(string $format): bool
    {
        static $machineFormats = null;

        if ($machineFormats === null) {
            $machineFormats = array_values(array_unique(array_filter([
                'U',
                'c',
                'r',
                \DATE_ATOM,
                \DATE_COOKIE,
                \DATE_ISO8601,
                defined('DATE_ISO8601_EXPANDED') ? \DATE_ISO8601_EXPANDED : null,
                \DATE_RFC822,
                \DATE_RFC850,
                \DATE_RFC1036,
                \DATE_RFC1123,
                'D, d M Y H:i:s \\G\\M\\T',
                \DATE_RFC2822,
                \DATE_RFC3339,
                \DATE_RFC3339_EXTENDED,
                \DATE_W3C,
            ], static fn ($value) => is_string($value) && $value !== '')));
        }

        return in_array($format, $machineFormats, true);
    }
}
