<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class Cf7Module extends AbstractModule
{
    use ShowsJalaliDates;

    public static function key(): string
    {
        return 'cf7';
    }

    public static function label(): string
    {
        return __('Contact Form 7', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali date picker for date fields, and Iranian fields such as mobile number and national ID.', 'persian-kit');
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
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'    => 'Contact Form 7',
            'slug'    => 'contact-form-7',
            'check'   => fn (): bool => $this->supportsContactForm7(),
            'version' => static fn (): ?string => defined('WPCF7_VERSION') ? (string) WPCF7_VERSION : null,
        ]];
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
        $container->register(Cf7FieldUsage::class, function () {
            return new Cf7FieldUsage();
        });
        $container->register(Cf7TagGenerator::class, function () {
            return new Cf7TagGenerator();
        });
    }

    /**
     * Forms render on the front end and submit through the REST API.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(Cf7DateField::class)->register();
        $container->get(Cf7InputNormalizer::class)->register();
        $container->get(Cf7IranianFields::class)->register();
        $container->get(Cf7FieldUsage::class)->register();

        // The form editor's buttons for the Iranian fields.
        if (is_admin()) {
            $container->get(Cf7TagGenerator::class)->register();
        }
    }

    /**
     * Forms that use the Iranian fields keep showing text inputs, and the
     * list of those forms stays current for the settings page's warning.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(Cf7IranianFields::class)->registerFallback();
        $container->get(Cf7FieldUsage::class)->register();
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
            'url'   => admin_url('admin.php?page=wpcf7&post=' . $form['id'] . '&action=edit'),
        ], (new Cf7FieldUsage())->formsUsingFields());
    }

    protected function supportsContactForm7(): bool
    {
        return defined('WPCF7_VERSION') && function_exists('wpcf7_add_form_tag');
    }
}
