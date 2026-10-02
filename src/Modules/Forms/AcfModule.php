<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class AcfModule extends AbstractModule
{
    use ShowsJalaliDates;

    public static function key(): string
    {
        return 'acf';
    }

    public static function label(): string
    {
        return __('ACF', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali date picker for Date Picker and Date Time Picker fields. Dates are still saved as Gregorian.', 'persian-kit');
    }

    public static function category(): ?string
    {
        return 'forms';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string, icon?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'    => 'ACF',
            'slug'    => 'advanced-custom-fields',
            'icon'    => 'icon.svg',
            'check'   => fn (): bool => $this->supportsAcf(),
            'version' => static fn (): ?string => defined('ACF_VERSION') ? (string) ACF_VERSION : null,
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(AcfDateFields::class, function () {
            return new AcfDateFields($this->showsJalaliDates());
        });
    }

    /**
     * Admin edit screens, acf_form() on the front end and templates. While
     * it is off, ACF's own date pickers are used, so nothing needs a fallback.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(AcfDateFields::class)->register();
    }

    protected function supportsAcf(): bool
    {
        return function_exists('acf_get_field_type');
    }
}
