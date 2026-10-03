<?php

namespace PersianKit\Service\Import\Settings;

defined('ABSPATH') || exit;

/**
 * How a source's setting maps to Persian Kit.
 */
enum SettingStatus: string
{
    /** Persian Kit does the same thing. */
    case Same = 'same';

    /** Persian Kit does nearly the same; the reason says how it differs. */
    case Close = 'close';

    /** Persian Kit has no match yet; nothing is imported. */
    case NotYet = 'not_yet';

    /** Persian Kit does it without a setting. */
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Same      => __('Same', 'persian-kit'),
            self::Close     => __('Close', 'persian-kit'),
            self::NotYet    => __('Not yet', 'persian-kit'),
            self::Automatic => __('Automatic', 'persian-kit'),
        };
    }

    /** Whether rows with this status change Persian Kit's settings. */
    public function imports(): bool
    {
        return $this === self::Same || $this === self::Close;
    }
}
