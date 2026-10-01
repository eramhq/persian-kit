<?php

namespace PersianKit\Modules\CharNormalization;

use PersianKit\Dependencies\Eram\Abzar\Exception\AbzarException;
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
        return __('Character Normalization', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Search finds words typed with either Arabic (ي ك) or Persian (ی ک) letters. Can also fix the letters when posts are saved.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => true, 'normalize_on_save' => false, 'teh_marbuta' => false];
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
        ];
    }

    public function boot(ServiceContainer $container): void
    {
        if ($this->setting('normalize_on_save') && apply_filters('persian_kit_char_normalization', true, 'wp_insert_post_data')) {
            add_filter('wp_insert_post_data', function (array $data, array $postarr) use ($container) {
                if (!$this->shouldNormalize($data, $postarr)) {
                    return $data;
                }

                $normalizer = $container->get(CharNormalizer::class);

                if (isset($data['post_title']) && is_string($data['post_title'])) {
                    $data['post_title'] = $normalizer->normalize($data['post_title']);
                }

                if (isset($data['post_excerpt']) && is_string($data['post_excerpt'])) {
                    $data['post_excerpt'] = $normalizer->normalize($data['post_excerpt']);
                }

                if (isset($data['post_content']) && is_string($data['post_content'])) {
                    try {
                        $data['post_content'] = $normalizer->normalizeContent($data['post_content']);
                    } catch (AbzarException) {
                        // Markup abzar cannot segment is saved as-is rather than blocking the save.
                    }
                }

                return $data;
            }, 10, 2);
        }

        if (apply_filters('persian_kit_char_normalization', true, 'posts_search')) {
            $container->get(SearchFilter::class)->register();
        }

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('persian-kit normalize', new CLI\NormalizeCommand(
                $container->get(NormalizationJobManager::class)
            ));
        }

        add_action('rest_api_init', function () use ($container) {
            $container->get(NormalizationRestController::class)->registerRoutes();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     */
    private function shouldNormalize(array $data, array $postarr): bool
    {
        $postType = $data['post_type'] ?? $postarr['post_type'] ?? '';
        if (!is_string($postType) || $postType === '') {
            return false;
        }

        $postTypeObject = get_post_type_object($postType);
        if (!$postTypeObject || empty($postTypeObject->public)) {
            return false;
        }

        $postStatus = $data['post_status'] ?? $postarr['post_status'] ?? '';
        if ($postStatus === 'auto-draft') {
            return false;
        }

        $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($postId > 0 && (wp_is_post_revision($postId) || wp_is_post_autosave($postId))) {
            return false;
        }

        $postContext = $postId > 0 ? get_post($postId) : null;
        if (!$postContext) {
            $postContext = (object) $postarr;
        }

        return (bool) apply_filters('persian_kit_should_normalize', true, $postContext, $data, $postarr);
    }
}
