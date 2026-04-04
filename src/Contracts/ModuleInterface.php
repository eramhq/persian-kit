<?php

namespace PersianKit\Contracts;

use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

interface ModuleInterface
{
    public static function key(): string;

    public static function defaults(): array;

    public function register(ServiceContainer $container): void;

    public function boot(ServiceContainer $container): void;
}
