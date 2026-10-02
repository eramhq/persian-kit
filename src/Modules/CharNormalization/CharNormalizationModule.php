<?php

namespace PersianKit\Modules\CharNormalization;

use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class CharNormalizationModule extends AbstractModule
{
    public static function key(): string
    {
        return 'char_normalization';
    }

    public static function label(): string
    {
        return __('Persian ی and ک', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Search matches Arabic or Persian letters and either kind of digit.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => true, 'normalize_on_save' => false, 'teh_marbuta' => false, 'half_space_fix' => false];
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/char-normalization-settings';
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(CharNormalizer::class, function () {
            return new CharNormalizer(
                tehMarbuta: (bool) $this->setting('teh_marbuta', false),
            );
        });

        $container->register(BatchMigrator::class, function (ServiceContainer $c) {
            return new BatchMigrator($c->get(CharNormalizer::class));
        });

        $container->register(NormalizationRestController::class, function (ServiceContainer $c) {
            return new NormalizationRestController($c->get(NormalizationJobManager::class));
        });

        $container->register(SearchFilter::class, function (ServiceContainer $c) {
            return new SearchFilter($c->get(CharNormalizer::class));
        });

        $container->register(NormalizationJobManager::class, function (ServiceContainer $c) {
            return new NormalizationJobManager($c->get(BatchMigrator::class));
        });

        $container->register(SaveNormalizer::class, function (ServiceContainer $c) {
            return new SaveNormalizer(
                $c->get(CharNormalizer::class),
                (bool) $this->setting('normalize_on_save'),
                (bool) $this->setting('half_space_fix'),
            );
        });
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        return [
            'enabled'           => !empty($values['enabled']),
            'normalize_on_save' => !empty($values['normalize_on_save']),
            'teh_marbuta'       => !empty($values['teh_marbuta']),
            'half_space_fix'    => !empty($values['half_space_fix']),
        ];
    }

    public function boot(ServiceContainer $container): void
    {
        $container->get(SaveNormalizer::class)->register();

        if (apply_filters('persian_kit_char_normalization', true, 'posts_search')) {
            $container->get(SearchFilter::class)->register();
        }

        add_action('rest_api_init', function () use ($container) {
            $container->get(NormalizationRestController::class)->registerRoutes();
        });
    }
}
