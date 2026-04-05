<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class NormalizationRestController
{
    private const NAMESPACE = 'persian-kit/v1';

    private BatchMigrator $migrator;

    public function __construct(BatchMigrator $migrator)
    {
        $this->migrator = $migrator;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/normalize/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'status'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/normalize/run', [
            'methods'             => 'POST',
            'callback'            => [$this, 'run'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'batch_size' => [
                    'type'              => 'integer',
                    'default'           => 50,
                    'minimum'           => 1,
                    'maximum'           => 500,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/normalize/restart', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restart'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function status(): \WP_REST_Response
    {
        $postTypes = get_post_types(['public' => true]);
        $cursor    = $this->migrator->getCursor();

        return new \WP_REST_Response([
            'cursor'      => $cursor,
            'is_resuming' => $cursor > 0,
            'counts'      => $this->migrator->countAffected(array_values($postTypes)),
        ]);
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response
    {
        $batchSize = $request->get_param('batch_size');
        $postTypes = array_values(get_post_types(['public' => true]));

        $result = $this->migrator->processBatch($postTypes, $batchSize);

        return new \WP_REST_Response([
            'processed' => $result->processed,
            'modified'  => $result->modified,
            'last_id'   => $result->lastId,
            'has_more'  => $result->hasMore,
        ]);
    }

    public function restart(): \WP_REST_Response
    {
        $this->migrator->clearCursor();

        return new \WP_REST_Response(['success' => true]);
    }
}
