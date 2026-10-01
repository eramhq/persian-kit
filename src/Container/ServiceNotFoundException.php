<?php

namespace PersianKit\Container;

defined('ABSPATH') || exit;

/**
 * Thrown by ServiceContainer::get() for an id that was never registered.
 */
class ServiceNotFoundException extends \RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('No service is registered as "%s".', $id));
    }
}
