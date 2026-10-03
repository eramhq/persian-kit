<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * persian-kit/v1/import/…: the Tools tab's switch. Administrators only.
 */
class ImportRestController
{
    private const NAMESPACE = 'persian-kit/v1';
    private const SOURCE = '(?P<source>[a-z0-9-]+)';

    private ImportRunner $runner;
    private ImportReview $review;
    private ImportReport $report;

    public function __construct(ImportRunner $runner, ImportReview $review, ImportReport $report)
    {
        $this->runner = $runner;
        $this->review = $review;
        $this->report = $report;
    }

    public function registerRoutes(): void
    {
        $this->route('/import/sources', 'GET', 'sources');
        $this->route('/import/' . self::SOURCE . '/review', 'GET', 'review');
        $this->route('/import/' . self::SOURCE . '/count', 'GET', 'count', ['task' => $this->stringArg(true)]);
        $this->route('/import/' . self::SOURCE . '/preview', 'GET', 'preview', [
            'task'  => $this->stringArg(true),
            'limit' => ['type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 50, 'sanitize_callback' => 'absint'],
        ]);
        $this->route('/import/' . self::SOURCE . '/report', 'GET', 'report', [
            'outcome' => ['type' => 'string', 'enum' => array_merge([''], ImportLog::OUTCOMES), 'default' => ''],
            'page'    => ['type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint'],
        ]);
        $this->route('/import/' . self::SOURCE . '/start', 'POST', 'start', [
            'rows'         => ['type' => 'array', 'items' => ['type' => 'string']],
            'tasks'        => ['type' => 'array', 'items' => ['type' => 'string']],
            'options'      => ['type' => 'object', 'default' => []],
            'backup'       => ['type' => 'boolean'],
            'acknowledged' => ['type' => 'array', 'items' => ['type' => 'string']],
        ]);
        $this->route('/import/run', 'POST', 'run', ['tab' => $this->stringArg(false)]);
        $this->route('/import/pause', 'POST', 'pause');
        $this->route('/import/restart', 'POST', 'restart');
        $this->route('/import/' . self::SOURCE . '/undo', 'POST', 'undo', [
            'cursor' => ['type' => 'integer', 'default' => 0, 'minimum' => 0, 'sanitize_callback' => 'absint'],
            'run_id' => $this->stringArg(false),
            'tab'    => $this->stringArg(false),
        ]);
        $this->route('/import/' . self::SOURCE . '/forget', 'POST', 'forget');
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function sources(): \WP_REST_Response
    {
        $job = ImportJob::load();

        return new \WP_REST_Response(['sources' => $this->review->sources(), 'job' => $job?->toPublic()]);
    }

    public function review(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(fn (): array => $this->review->review($this->source($request)));
    }

    public function count(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(fn (): array => $this->review->task($this->source($request), (string) $request->get_param('task'), 0));
    }

    public function preview(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(fn (): array => $this->review->task($this->source($request), (string) $request->get_param('task'), (int) $request->get_param('limit')));
    }

    public function report(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(function () use ($request): array {
            $outcome = (string) $request->get_param('outcome');

            return $this->report->page($this->source($request), $outcome === '' ? null : $outcome, (int) $request->get_param('page'));
        });
    }

    /**
     * Saves Review's choices. 409, with the job and the reason, while the
     * source still has to be deactivated.
     */
    public function start(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(function () use ($request): array {
            $source = $this->source($request);
            $choices = [];
            foreach (['rows', 'tasks', 'acknowledged'] as $key) {
                if (is_array($request->get_param($key))) {
                    $choices[$key] = array_values(array_filter($request->get_param($key), 'is_string'));
                }
            }
            if (is_array($request->get_param('options'))) {
                $choices['options'] = $this->sanitizeOptions($request->get_param('options'));
            }
            if ($request->get_param('backup') !== null) {
                $choices['backup'] = (bool) $request->get_param('backup');
            }

            $job = $this->runner->start($source, $choices);

            if ($job->step === ImportJob::STEP_DEACTIVATE) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
                throw new ImportException(ImportException::NOT_READY, (string) $source->readyToImport(), 409);
            }

            return ['job' => $job->toPublic()];
        });
    }

    public function run(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(fn (): array => ['job' => $this->runner->run($this->owner($request))->toPublic()]);
    }

    public function pause(): \WP_REST_Response
    {
        return new \WP_REST_Response(['job' => $this->runner->pause(__('Paused.', 'persian-kit'))?->toPublic()]);
    }

    public function restart(): \WP_REST_Response
    {
        $this->runner->cancel();

        return new \WP_REST_Response(['job' => null]);
    }

    public function undo(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(fn (): array => $this->runner->undo(
            $this->source($request),
            $this->owner($request),
            (int) $request->get_param('cursor'),
            (string) $request->get_param('run_id')
        ));
    }

    public function forget(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        return $this->handle(function () use ($request): array {
            $source = $this->source($request);
            $job = ImportJob::load();
            if ($job !== null && $job->source === $source->key() && !$job->isFinished()) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- REST and WP-CLI show it as text.
                throw new ImportException(ImportException::OTHER_JOB, __('Finish or cancel the switch first.', 'persian-kit'), 409);
            }

            $this->runner->forget($source);

            return ['forgotten' => true];
        });
    }

    /**
     * @param callable(): array<string, mixed> $callback
     */
    private function handle(callable $callback): \WP_REST_Response|\WP_Error
    {
        try {
            return new \WP_REST_Response($callback());
        } catch (ImportException $error) {
            $job = ImportJob::load();

            return new \WP_Error($error->errorCode, $error->getMessage(), [
                'status' => $error->status,
                'job'    => $job?->toPublic(),
            ]);
        }
    }

    private function source(\WP_REST_Request $request): Source
    {
        return $this->runner->source((string) $request->get_param('source'));
    }

    /**
     * Each tab is its own owner of the lock, so a second tab waits.
     */
    private function owner(\WP_REST_Request $request): string
    {
        $tab = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $request->get_param('tab')));

        return 'rest:' . get_current_user_id() . ':' . $tab;
    }

    /**
     * Review's other choices: the status map and the district line.
     *
     * @param array<mixed> $options
     * @return array<string, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        $clean = [];

        if (isset($options['status_map']) && is_array($options['status_map'])) {
            $clean['status_map'] = [];
            foreach ($options['status_map'] as $from => $to) {
                if (is_string($from) && is_string($to)) {
                    $clean['status_map'][sanitize_key($from)] = sanitize_key($to);
                }
            }
        }

        foreach (['district_line', 'keep_source', 'fix_double_dates'] as $flag) {
            if (array_key_exists($flag, $options)) {
                $clean[$flag] = (bool) $options[$flag];
            }
        }

        return $clean;
    }

    /**
     * @param array<string, array<string, mixed>> $args
     */
    private function route(string $path, string $method, string $callback, array $args = []): void
    {
        register_rest_route(self::NAMESPACE, $path, [
            'methods'             => $method,
            'callback'            => [$this, $callback],
            'permission_callback' => [$this, 'checkPermission'],
            'args'                => $args,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stringArg(bool $required): array
    {
        $arg = ['type' => 'string', 'required' => $required, 'sanitize_callback' => 'sanitize_text_field'];

        return $required ? $arg : $arg + ['default' => ''];
    }
}
