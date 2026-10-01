<?php

namespace PersianKit\Tests\Unit\CharNormalization\CLI;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Modules\CharNormalization\BatchMigrator;
use PersianKit\Modules\CharNormalization\CLI\NormalizeCommand;
use PersianKit\Modules\CharNormalization\NormalizationJobManager;
use PHPUnit\Framework\TestCase;

class NormalizeCommandTest extends TestCase
{
    private array $ticks = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        \WP_CLI::$messages = [];
        $this->ticks = [];

        Functions\when('WP_CLI\Utils\get_flag_value')->alias(
            static fn (array $args, string $flag, $default = null) => $args[$flag] ?? $default
        );
        Functions\when('WP_CLI\Utils\make_progress_bar')->alias(function () {
            $ticks = &$this->ticks;

            return new class($ticks) {
                public function __construct(private array &$ticks) {}

                public function tick(int $n = 1): void
                {
                    $this->ticks[] = $n;
                }

                public function finish(): void
                {
                }
            };
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_run_goes_through_the_job_manager(): void
    {
        $jobs = Mockery::mock(NormalizationJobManager::class);
        $jobs->shouldReceive('status')->once()->with(['post', 'page'])->andReturn([
            'cursor' => 0, 'is_resuming' => false, 'job' => ['status' => 'idle'],
        ]);
        $jobs->shouldReceive('runBatch')->twice()->with(['post', 'page'], 100)->andReturn(
            ['has_more' => true, 'job' => ['processed' => 100, 'modified' => 3]],
            ['has_more' => false, 'job' => ['processed' => 140, 'modified' => 5]],
        );

        $this->command($jobs)([], []);

        $this->assertSame([100, 40], $this->ticks);
        $this->assertContains(['success', 'Done! 140 posts processed, 5 modified.'], \WP_CLI::$messages);
    }

    public function test_resumed_job_counts_only_new_progress(): void
    {
        $jobs = Mockery::mock(NormalizationJobManager::class);
        $jobs->shouldReceive('status')->andReturn([
            'cursor' => 812, 'is_resuming' => true, 'job' => ['status' => 'running', 'processed' => 300],
        ]);
        $jobs->shouldReceive('runBatch')->once()->andReturn(['has_more' => false, 'job' => ['processed' => 350, 'modified' => 1]]);

        $this->command($jobs)([], []);

        $this->assertSame([50], $this->ticks);
        $this->assertContains(['log', 'Resuming from post ID 812...'], \WP_CLI::$messages);
    }

    public function test_batch_size_is_clamped_to_at_least_one(): void
    {
        $jobs = Mockery::mock(NormalizationJobManager::class);
        $jobs->shouldReceive('status')->andReturn(['cursor' => 0, 'is_resuming' => false, 'job' => []]);
        $jobs->shouldReceive('runBatch')->once()->with(['post'], 1)->andReturn(['has_more' => false, 'job' => ['processed' => 0, 'modified' => 0]]);

        $this->command($jobs)([], ['post-type' => 'post', 'batch-size' => '-5']);

        $this->assertTrue(true);
    }

    public function test_restart_clears_the_job(): void
    {
        $jobs = Mockery::mock(NormalizationJobManager::class);
        $jobs->shouldReceive('restart')->once();
        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('countAffected')->once()->andReturn(['post' => 2]);
        Functions\when('WP_CLI\Utils\format_items')->justReturn(null);

        (new NormalizeCommand($migrator, $jobs))([], ['restart' => true, 'dry-run' => true, 'post-type' => 'post']);

        $this->assertContains(['log', 'Total: 2 posts need normalization.'], \WP_CLI::$messages);
    }

    private function command(NormalizationJobManager $jobs): NormalizeCommand
    {
        return new NormalizeCommand(Mockery::mock(BatchMigrator::class), $jobs);
    }
}
