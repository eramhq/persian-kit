<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class NormalizationRestController
{
    private const NAMESPACE = 'persian-kit/v1';

    private NormalizationJobManager $jobs;

    public function __construct(NormalizationJobManager $jobs)
    {
        $this->jobs = $jobs;
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
        return new \WP_REST_Response(
            $this->jobs->status(array_values(get_post_types(['public' => true])))
        );
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response($this->jobs->runBatch(
            array_values(get_post_types(['public' => true])),
            (int) $request->get_param('batch_size')
        ));
    }

    public function restart(): \WP_REST_Response
    {
        $this->jobs->restart();

        return new \WP_REST_Response(['success' => true]);
    }
}
