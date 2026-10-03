<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\SourceRegistry;
use PersianKit\Tests\Integration\Support\FakeImportSource;
use PersianKit\Tests\Integration\Support\FakeImportTask;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class ImportRestTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    private FakeImportSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new FakeImportSource();
        add_filter('persian_kit_import_sources', fn (array $sources): array => array_merge($sources, [$this->source]));
        Bootstrap::get(SourceRegistry::class)->reset();
        $this->setUpImportLog();
        update_option(FakeImportTask::OPTION, [1 => 'old-1', 2 => 'old-2']);

        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action('rest_api_init', $wp_rest_server);
    }

    protected function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        Bootstrap::get(SourceRegistry::class)->reset();
        parent::tearDown();
    }

    public function test_only_administrators_can_use_it(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        foreach ([['GET', '/persian-kit/v1/import/sources'], ['GET', '/persian-kit/v1/import/fake-source/review'], ['POST', '/persian-kit/v1/import/run']] as [$method, $route]) {
            $this->assertSame(403, rest_do_request(new \WP_REST_Request($method, $route))->get_status(), $route);
        }
    }

    public function test_sources_and_review(): void
    {
        $this->asAdmin();

        $sources = rest_do_request(new \WP_REST_Request('GET', '/persian-kit/v1/import/sources'))->get_data();
        $fake = array_values(array_filter($sources['sources'], static fn (array $source): bool => $source['key'] === 'fake-source'));
        $this->assertSame('inactive', $fake[0]['state']);

        $review = rest_do_request(new \WP_REST_Request('GET', '/persian-kit/v1/import/fake-source/review'))->get_data();
        $this->assertSame(['digits', 'dual'], array_column($review['rows'], 'id'));
        $this->assertSame(['same', 'not_yet'], array_column($review['rows'], 'status'));
        $this->assertSame(2, $review['tasks'][0]['count']);
        $this->assertSame(['label' => 'Item 1', 'before' => 'old-1', 'after' => 'new-1'], $review['tasks'][0]['samples'][0]);

        // Review changes nothing.
        $this->assertSame('old-1', get_option(FakeImportTask::OPTION)[1]);
        $this->assertNull(ImportJob::load());
    }

    public function test_start_answers_409_while_the_source_is_active_and_keeps_the_choices(): void
    {
        $this->asAdmin();
        $this->source->active = true;

        $request = new \WP_REST_Request('POST', '/persian-kit/v1/import/fake-source/start');
        $request->set_body_params(['rows' => ['digits'], 'options' => ['district_line' => false, 'unknown' => 'x'], 'backup' => true]);
        $response = rest_do_request($request);

        $this->assertSame(409, $response->get_status());
        $this->assertSame('persian_kit_import_not_ready', $response->get_data()['code']);
        $this->assertSame('deactivate', $response->get_data()['data']['job']['step']);

        $job = ImportJob::load();
        $this->assertSame(['digits'], $job->rows);
        $this->assertSame(['district_line' => false], $job->options);
        $this->assertTrue($job->backup);
    }

    public function test_start_run_report_and_undo(): void
    {
        $this->asAdmin();

        $start = new \WP_REST_Request('POST', '/persian-kit/v1/import/fake-source/start');
        $start->set_body_params(['rows' => []]);
        $this->assertSame(200, rest_do_request($start)->get_status());

        $run = new \WP_REST_Request('POST', '/persian-kit/v1/import/run');
        $run->set_body_params(['tab' => 'abc']);
        $job = rest_do_request($run)->get_data()['job'];
        $this->assertSame('report', $job['step']);
        $this->assertArrayNotHasKey('snapshot', $job);

        $report = rest_do_request(new \WP_REST_Request('GET', '/persian-kit/v1/import/fake-source/report'))->get_data();
        $this->assertSame(2, $report['counts']['changed']);
        $this->assertSame('Done', $report['rows'][1]['outcome_label']);

        $undo = new \WP_REST_Request('POST', '/persian-kit/v1/import/fake-source/undo');
        $result = rest_do_request($undo)->get_data();
        $this->assertTrue($result['done']);
        $this->assertSame(2, $result['restored']);
    }

    public function test_an_unknown_source_is_a_404(): void
    {
        $this->asAdmin();

        $this->assertSame(404, rest_do_request(new \WP_REST_Request('GET', '/persian-kit/v1/import/nope/review'))->get_status());
    }

    private function asAdmin(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }
}
