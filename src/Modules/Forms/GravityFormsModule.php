<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class GravityFormsModule extends AbstractModule
{
    use ShowsJalaliDates;

    public static function key(): string
    {
        return 'gravityforms';
    }

    public static function label(): string
    {
        return __('Gravity Forms', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali dates in Date fields, an "Iranian fields" group with checks for mobile number, national ID and more, an Iran address type, and English digits in phone, number and date inputs.', 'persian-kit');
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
     * 2.9 is the oldest release whose Date field markup and hooks this was
     * built against: its jQuery UI picker, and 3.0's Apex picker.
     *
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string, url?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'       => 'Gravity Forms',
            // Gravity Forms is not on WordPress.org.
            'url'        => 'https://www.gravityforms.com/',
            'check'      => fn (): bool => $this->supportsGravityForms(),
            'version'    => static fn (): ?string => class_exists('GFForms') ? (string) \GFForms::$version : null,
            'minVersion' => '2.9',
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(GravityFormsDateField::class, function () {
            return new GravityFormsDateField($this->showsJalaliDates());
        });
        $container->register(GravityFormsInputNormalizer::class, function () {
            return new GravityFormsInputNormalizer();
        });
        $container->register(GravityFormsEntryDates::class, function () {
            return new GravityFormsEntryDates();
        });
        $container->register(GravityFormsIranianFields::class, function () {
            return new GravityFormsIranianFields();
        });
        $container->register(GravityFormsAddress::class, function () {
            return new GravityFormsAddress();
        });
        $container->register(GravityFormsFieldUsage::class, function () {
            return new GravityFormsFieldUsage();
        });
    }

    /**
     * Forms render on the front end, or over AJAX, and are sent to their
     * page, to admin-ajax, or through GFAPI.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(GravityFormsDateField::class)->register();
        $container->get(GravityFormsInputNormalizer::class)->register();
        $container->get(GravityFormsIranianFields::class)->register();
        $container->get(GravityFormsAddress::class)->register();
        $container->get(GravityFormsFieldUsage::class)->register();

        // Submitted on, updated and note dates.
        if ($this->showsJalaliDates() && is_admin()) {
            $container->get(GravityFormsEntryDates::class)->register();
        }
    }

    /**
     * While it is off, forms keep showing their Iranian fields, as text
     * inputs without checks, and their Iran addresses, and the list of the
     * forms with Iranian fields stays current for the settings page's
     * warning.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(GravityFormsIranianFields::class)->registerFallback();
        $container->get(GravityFormsAddress::class)->registerFallback();
        $container->get(GravityFormsFieldUsage::class)->register();
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    public function formsUsingFields(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return array_map(static fn (array $form): array => [
            'title' => $form['title'],
            'url'   => admin_url('admin.php?page=gf_edit_forms&id=' . $form['id']),
        ], (new GravityFormsFieldUsage())->formsUsingFields());
    }

    protected function supportsGravityForms(): bool
    {
        return class_exists('GFForms');
    }
}
