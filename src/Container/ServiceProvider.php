<?php

namespace PersianKit\Container;

defined('ABSPATH') || exit;

interface ServiceProvider
{
    public function register(ServiceContainer $container): void;

    public function boot(ServiceContainer $container): void;
}
