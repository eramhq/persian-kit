<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class WPFormsModule extends AbstractModule
{
    public static function key(): string
    {
        return 'wpforms';
    }

    public static function label(): string
    {
        return __('WPForms', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Iranian fields such as mobile number and national ID, and a Jalali date field, also in WPForms Lite.', 'persian-kit');
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
     * 1.9.1 is the oldest release with wpforms()->obj(), which the fields
     * use to report errors and save values.
     *
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string, url?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'       => 'WPForms',
            'slug'       => 'wpforms-lite',
            'check'      => fn (): bool => $this->supportsWPForms(),
            'version'    => static fn (): ?string => defined('WPFORMS_VERSION') ? (string) WPFORMS_VERSION : null,
            'minVersion' => '1.9.1',
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(WPFormsIranianFields::class, function () {
            return new WPFormsIranianFields();
        });
        $container->register(WPFormsDateField::class, function () {
            return new WPFormsDateField();
        });
        $container->register(WPFormsInputNormalizer::class, function () {
            return new WPFormsInputNormalizer();
        });
        $container->register(WPFormsFieldUsage::class, function () {
            return new WPFormsFieldUsage();
        });
    }

    /**
     * Forms render on the front end and are sent to their page or to
     * admin-ajax.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(WPFormsIranianFields::class)->register();
        $container->get(WPFormsDateField::class)->register();
        $container->get(WPFormsInputNormalizer::class)->register();
        $container->get(WPFormsFieldUsage::class)->register();
    }

    /**
     * While it is off, forms keep showing the Iranian fields and Jalali
     * date fields, as text inputs without checks, and the list of the forms
     * that use them stays current for the settings page's warning.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(WPFormsIranianFields::class)->registerFallback();
        $container->get(WPFormsDateField::class)->registerFallback();
        $container->get(WPFormsFieldUsage::class)->register();
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
            'url'   => admin_url('admin.php?page=wpforms-builder&view=fields&form_id=' . $form['id']),
        ], (new WPFormsFieldUsage())->formsUsingFields());
    }

    protected function supportsWPForms(): bool
    {
        return defined('WPFORMS_VERSION');
    }
}
