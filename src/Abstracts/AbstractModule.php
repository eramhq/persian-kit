<?php

namespace PersianKit\Abstracts;

use PersianKit\Contracts\ModuleInterface;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;

defined('ABSPATH') || exit;

abstract class AbstractModule implements ModuleInterface
{
    protected SettingsManager $settings;

    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->module(static::key(), 'enabled', false);
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings->module(static::key(), $key, $default);
    }
}
