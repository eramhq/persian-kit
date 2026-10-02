<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Modules\DateConversion\DateConversionModule;

defined('ABSPATH') || exit;

class FormsModule extends AbstractModule
{
    public static function key(): string
    {
        return 'forms';
    }

    public static function label(): string
    {
        return __('Forms', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali date picker for Contact Form 7 and ACF. Dates are still saved as Gregorian.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'cf7'     => true,
            'acf'     => true,
        ];
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/forms-settings';
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(Cf7DateField::class, function () {
            return new Cf7DateField($this->showsJalaliDates());
        });
        $container->register(Cf7InputNormalizer::class, function () {
            return new Cf7InputNormalizer();
        });
        $container->register(Cf7IranianFields::class, function () {
            return new Cf7IranianFields();
        });
        $container->register(AcfDateFields::class, function () {
            return new AcfDateFields($this->showsJalaliDates());
        });
    }

    public function boot(ServiceContainer $container): void
    {
        // Forms render on the front end and submit through the REST API.
        if ($this->setting('cf7') && $this->supportsContactForm7()) {
            $container->get(Cf7DateField::class)->register();
            $container->get(Cf7InputNormalizer::class)->register();
            $container->get(Cf7IranianFields::class)->register();
        }

        // Admin edit screens, acf_form() on the front end and templates.
        if ($this->setting('acf') && $this->supportsAcf()) {
            $container->get(AcfDateFields::class)->register();
        }
    }

    /**
     * Dates are shown as Jalali dates where people read them, such as in
     * emails and ACF values in templates, only while Date Conversion is on.
     */
    private function showsJalaliDates(): bool
    {
        return (bool) $this->settings->module(DateConversionModule::key(), 'enabled', true);
    }

    /**
     * @return list<string>
     */
    public function inactivePlugins(): array
    {
        $inactive = [];
        if (!$this->supportsContactForm7()) {
            $inactive[] = 'Contact Form 7';
        }
        if (!$this->supportsAcf()) {
            $inactive[] = 'ACF';
        }

        return $inactive;
    }

    protected function supportsContactForm7(): bool
    {
        return defined('WPCF7_VERSION') && function_exists('wpcf7_add_form_tag');
    }

    protected function supportsAcf(): bool
    {
        return function_exists('acf_get_field_type');
    }
}
