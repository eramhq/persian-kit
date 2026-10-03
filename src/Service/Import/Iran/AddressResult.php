<?php

namespace PersianKit\Service\Import\Iran;

defined('ABSPATH') || exit;

/**
 * An address as WooCommerce reads it: the province code, the city name and
 * the district, if any. Attention means the address is left as it is.
 */
final class AddressResult
{
    public const CHANGED = 'changed';
    public const UNCHANGED = 'unchanged';
    public const ATTENTION = 'attention';

    public function __construct(
        public readonly string $outcome,
        public readonly string $state,
        public readonly string $city,
        public readonly string $district = '',
        public readonly string $reason = '',
    ) {
    }

    public static function attention(string $reason): self
    {
        return new self(self::ATTENTION, '', '', '', $reason);
    }
}
