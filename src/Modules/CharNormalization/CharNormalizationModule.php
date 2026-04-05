<?php

namespace PersianKit\Modules\CharNormalization;

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
        return __('Character Normalization', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Normalizes Arabic characters (Yeh, Kaf) to Persian', 'persian-kit');
    }

    public static function defaults(): array
    {
        return ['enabled' => true, 'teh_marbuta' => false];
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/char-normalization-settings';
    }

    public function register(ServiceContainer $container): void
    {
        $container->register(CharNormalizer::class, function () {
            return new CharNormalizer((bool) $this->setting('teh_marbuta', false));
        });

        $container->register(BatchMigrator::class, function (ServiceContainer $c) {
            return new BatchMigrator($c->get(CharNormalizer::class));
        });

        $container->register(NormalizationRestController::class, function (ServiceContainer $c) {
            return new NormalizationRestController($c->get(BatchMigrator::class));
        });
    }

    public function boot(ServiceContainer $container): void
    {
        if (apply_filters('persian_kit_char_normalization', true, 'wp_insert_post_data')) {
            add_filter('wp_insert_post_data', function (array $data, array $postarr) use ($container) {
                if (!apply_filters('persian_kit_should_normalize', true, $data)) {
                    return $data;
                }

                $normalizer = $container->get(CharNormalizer::class);

                $data['post_title']   = $normalizer->normalize($data['post_title']);
                $data['post_excerpt'] = $normalizer->normalize($data['post_excerpt']);
                $data['post_content'] = $normalizer->normalizeContent($data['post_content']);

                return $data;
            }, 10, 2);
        }

        if (apply_filters('persian_kit_char_normalization', true, 'pre_get_posts')) {
            add_action('pre_get_posts', function ($query) use ($container) {
                if (!$query->is_search() || !$query->is_main_query()) {
                    return;
                }

                $searchTerm = $query->get('s');
                if ($searchTerm === '') {
                    return;
                }

                $normalizer = $container->get(CharNormalizer::class);
                $query->set('s', $normalizer->normalizeForSearch($searchTerm));
            });
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('persian-kit normalize', new CLI\NormalizeCommand(
                $container->get(BatchMigrator::class)
            ));
        }

        add_action('rest_api_init', function () use ($container) {
            $container->get(NormalizationRestController::class)->registerRoutes();
        });
    }
}
