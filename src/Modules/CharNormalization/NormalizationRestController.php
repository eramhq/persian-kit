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

        register_rest_route(self::NAMESPACE, '/normalize/preview', [
            'methods'             => 'GET',
            'callback'            => [$this, 'preview'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'post_types' => $this->postTypesArg(),
                'cursor'     => [
                    'type'              => 'integer',
                    'default'           => 0,
                    'minimum'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'batch_size' => $this->batchSizeArg(200),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/normalize/run', [
            'methods'             => 'POST',
            'callback'            => [$this, 'run'],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => [
                'post_types' => $this->postTypesArg(),
                'batch_size' => $this->batchSizeArg(50),
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
            $this->jobs->status($this->publicPostTypes())
        );
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postTypes = $this->requestedPostTypes($request);
        if ($postTypes instanceof \WP_Error) {
            return $postTypes;
        }

        return new \WP_REST_Response($this->jobs->preview(
            $postTypes,
            (int) $request->get_param('cursor'),
            (int) $request->get_param('batch_size')
        ));
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postTypes = $this->requestedPostTypes($request);
        if ($postTypes instanceof \WP_Error) {
            return $postTypes;
        }

        return new \WP_REST_Response($this->jobs->runBatch(
            $postTypes,
            (int) $request->get_param('batch_size')
        ));
    }

    public function restart(): \WP_REST_Response
    {
        $this->jobs->restart();

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * The requested post types that are public; all public types when none
     * were sent. An explicit list with no public type in it is an error.
     *
     * @return array<string>|\WP_Error
     */
    private function requestedPostTypes(\WP_REST_Request $request): array|\WP_Error
    {
        $publicTypes = $this->publicPostTypes();
        $requested = $request->get_param('post_types');

        if (!is_array($requested) || $requested === []) {
            return $publicTypes;
        }

        $postTypes = array_values(array_intersect($publicTypes, $requested));

        if ($postTypes === []) {
            return new \WP_Error(
                'persian_kit_invalid_post_types',
                __('Choose at least one public post type.', 'persian-kit'),
                ['status' => 400]
            );
        }

        return $postTypes;
    }

    /**
     * @return array<string>
     */
    private function publicPostTypes(): array
    {
        return array_values(get_post_types(['public' => true]));
    }

    /**
     * @return array<string, mixed>
     */
    private function postTypesArg(): array
    {
        return [
            'type'    => 'array',
            'items'   => ['type' => 'string'],
            'default' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchSizeArg(int $default): array
    {
        return [
            'type'              => 'integer',
            'default'           => $default,
            'minimum'           => 1,
            'maximum'           => 500,
            'sanitize_callback' => 'absint',
        ];
    }
}
