<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Modules\CharNormalization\BatchMigrator;
use PersianKit\Modules\CharNormalization\BatchResult;
use PersianKit\Modules\CharNormalization\NormalizationJobManager;
use PHPUnit\Framework\TestCase;

class NormalizationJobManagerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->options = [];

        Functions\when('get_option')->alias(function (string $key, $default = false) {
            return $this->options[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function (string $key, $value) {
            $this->options[$key] = $value;
            return true;
        });

        Functions\when('delete_option')->alias(function (string $key) {
            unset($this->options[$key]);
            return true;
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    public function test_start_creates_running_job_and_schedules_batch(): void
    {
        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('getCursor')->twice()->andReturn(0);
        $migrator->shouldReceive('countAffected')->once()->with(['post'])->andReturn(['post' => 2]);
        $migrator->shouldReceive('processBatch')
            ->once()
            ->with(['post'], 50)
            ->andReturn(new BatchResult(5, 2, 5, true));

        $jobs = new NormalizationJobManager($migrator);
        $result = $jobs->runBatch(['post'], 50);
        $state = $result['job'];

        $this->assertSame('running', $state['status']);
        $this->assertSame(['post'], $state['post_types']);
        $this->assertSame(50, $state['batch_size']);
        $this->assertSame(['post' => 2], $state['counts']);
        $this->assertTrue($result['has_more']);
        $this->assertFalse($result['is_resuming']);
        $this->assertSame(5, $state['processed']);
        $this->assertSame(2, $state['modified']);
        $this->assertSame('running', $this->options[NormalizationJobManager::STATE_OPTION]['status']);
    }

    public function test_run_batch_continues_existing_state_when_more_work_remains(): void
    {
        $this->options[NormalizationJobManager::STATE_OPTION] = [
            'status'     => 'running',
            'post_types' => ['post'],
            'batch_size' => 50,
            'processed'  => 10,
            'modified'   => 4,
        ];

        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('getCursor')->twice()->andReturn(15);
        $migrator->shouldReceive('processBatch')
            ->once()
            ->with(['post'], 50)
            ->andReturn(new BatchResult(5, 2, 15, true));

        $jobs = new NormalizationJobManager($migrator);
        $result = $jobs->runBatch(['post'], 50);

        $state = $this->options[NormalizationJobManager::STATE_OPTION];
        $this->assertSame('running', $state['status']);
        $this->assertSame(15, $state['processed']);
        $this->assertSame(6, $state['modified']);
        $this->assertSame(15, $state['last_id']);
        $this->assertTrue($result['has_more']);
        $this->assertTrue($result['is_resuming']);
    }

    public function test_run_batch_marks_job_completed_and_clears_cursor(): void
    {
        $this->options[NormalizationJobManager::STATE_OPTION] = [
            'status'     => 'running',
            'post_types' => ['post'],
            'batch_size' => 50,
            'processed'  => 0,
            'modified'   => 0,
        ];

        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('getCursor')->twice()->andReturn(0);
        $migrator->shouldReceive('processBatch')
            ->once()
            ->with(['post'], 50)
            ->andReturn(new BatchResult(3, 1, 3, false));
        $migrator->shouldReceive('clearCursor')->once();

        $jobs = new NormalizationJobManager($migrator);
        $result = $jobs->runBatch(['post'], 50);

        $state = $this->options[NormalizationJobManager::STATE_OPTION];
        $this->assertSame('completed', $state['status']);
        $this->assertSame(3, $state['processed']);
        $this->assertSame(1, $state['modified']);
        $this->assertSame(3, $state['last_id']);
        $this->assertFalse($result['has_more']);
        $this->assertTrue($result['is_resuming']);
    }

    public function test_preview_is_a_dry_run_from_the_given_cursor(): void
    {
        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('processBatch')
            ->once()
            ->with(['post', 'page'], 200, true, 15)
            ->andReturn(new BatchResult(200, 3, 215, true, ['post' => 3]));

        $result = (new NormalizationJobManager($migrator))->preview(['post', 'page'], 15, 200);

        $this->assertSame(['post' => 3, 'page' => 0], $result['counts']);
        $this->assertSame(215, $result['last_id']);
        $this->assertTrue($result['has_more']);
        $this->assertSame([], $this->options);
    }

    public function test_resumed_job_keeps_its_own_post_types(): void
    {
        $this->options[NormalizationJobManager::STATE_OPTION] = [
            'status'     => 'running',
            'post_types' => ['page'],
            'processed'  => 10,
            'modified'   => 2,
        ];

        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('getCursor')->andReturn(10);
        $migrator->shouldReceive('processBatch')
            ->once()
            ->with(['page'], 50)
            ->andReturn(new BatchResult(5, 0, 15, true));

        $result = (new NormalizationJobManager($migrator))->runBatch(['post'], 50);

        $this->assertSame(['page'], $result['job']['post_types']);
    }

    public function test_status_does_not_count_posts(): void
    {
        $migrator = Mockery::mock(BatchMigrator::class);
        $migrator->shouldReceive('getCursor')->andReturn(0);
        $migrator->shouldReceive('countAffected')->never();

        $status = (new NormalizationJobManager($migrator))->status(['post']);

        $this->assertSame('idle', $status['job']['status']);
        $this->assertSame([], $status['counts']);
    }
}
