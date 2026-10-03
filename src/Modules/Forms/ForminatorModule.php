<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class ForminatorModule extends AbstractModule
{
    use ShowsJalaliDates;

    public static function key(): string
    {
        return 'forminator';
    }

    public static function label(): string
    {
        return __('Forminator', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Jalali date picker for Calendar date fields, and Iranian checks for fields with a persian-kit class.', 'persian-kit');
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
     * 1.50 is the oldest release with every hook this uses: the form's
     * scripts action and the email context.
     *
     * @return list<array{name: string, check: callable(): bool, version?: callable(): ?string, minVersion?: string, slug?: string}>
     */
    public function requiredPlugins(): array
    {
        return [[
            'name'       => 'Forminator',
            'slug'       => 'forminator',
            'check'      => fn (): bool => $this->supportsForminator(),
            'version'    => static fn (): ?string => defined('FORMINATOR_VERSION') ? (string) FORMINATOR_VERSION : null,
            'minVersion' => '1.50',
        ]];
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(ForminatorDateField::class, function () {
            return new ForminatorDateField($this->showsJalaliDates());
        });
        $container->register(ForminatorInputNormalizer::class, function () {
            return new ForminatorInputNormalizer();
        });
        $container->register(ForminatorIranianFields::class, function () {
            return new ForminatorIranianFields();
        });
        $container->register(ForminatorFieldUsage::class, function () {
            return new ForminatorFieldUsage();
        });
    }

    /**
     * Forms render on the front end, or over AJAX, and submit to admin-ajax
     * or to the page itself.
     */
    public function boot(ServiceContainer $container): void
    {
        $container->get(ForminatorDateField::class)->register();
        $container->get(ForminatorInputNormalizer::class)->register();
        $container->get(ForminatorIranianFields::class)->register();
        $container->get(ForminatorFieldUsage::class)->register();
    }

    /**
     * While it is off, the fields are plain Forminator fields again. The
     * list of forms that use the persian-kit classes stays current for the
     * settings page's warning.
     */
    public function bootDisabled(ServiceContainer $container): void
    {
        $container->get(ForminatorFieldUsage::class)->register();
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
            'url'   => admin_url('admin.php?page=forminator-cform-wizard&id=' . $form['id']),
        ], (new ForminatorFieldUsage())->formsUsingFields());
    }

    protected function supportsForminator(): bool
    {
        return defined('FORMINATOR_VERSION');
    }
}
