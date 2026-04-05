<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Modules\CharNormalization\BatchMigrator;
use PersianKit\Modules\CharNormalization\BatchResult;
use PersianKit\Modules\CharNormalization\CharNormalizer;

class BatchMigratorTest extends TestCase
{
    private CharNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->normalizer = new CharNormalizer();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeMigrator(): BatchMigrator
    {
        return new BatchMigrator($this->normalizer);
    }

    public function test_get_cursor_returns_zero_when_no_option(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('persian_kit_normalize_cursor', 0)
            ->andReturn(0);

        $migrator = $this->makeMigrator();
        $this->assertSame(0, $migrator->getCursor());
    }

    public function test_get_cursor_returns_stored_value(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('persian_kit_normalize_cursor', 0)
            ->andReturn(42);

        $migrator = $this->makeMigrator();
        $this->assertSame(42, $migrator->getCursor());
    }

    public function test_clear_cursor_deletes_option(): void
    {
        Functions\expect('delete_option')
            ->once()
            ->with('persian_kit_normalize_cursor');

        $migrator = $this->makeMigrator();
        $migrator->clearCursor();

        $this->assertTrue(true); // Verified by expectation
    }

    public function test_process_batch_dry_run_does_not_modify(): void
    {
        Functions\expect('get_option')
            ->with('persian_kit_normalize_cursor', 0)
            ->andReturn(0);

        Functions\expect('update_option')->never();

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT ...');
        $wpdb->shouldReceive('get_results')->once()->andReturn([
            (object) [
                'ID'           => 1,
                'post_title'   => "كتاب",
                'post_content' => "كتاب",
                'post_excerpt' => '',
            ],
        ]);

        $GLOBALS['wpdb'] = $wpdb;

        $migrator = $this->makeMigrator();
        $result = $migrator->processBatch(['post'], 100, dryRun: true);

        $this->assertInstanceOf(BatchResult::class, $result);
        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->modified);
        $this->assertFalse($result->hasMore);

        unset($GLOBALS['wpdb']);
    }

    public function test_process_batch_returns_batch_result(): void
    {
        Functions\expect('get_option')
            ->with('persian_kit_normalize_cursor', 0)
            ->andReturn(0);

        Functions\expect('update_option')->once();
        Functions\expect('clean_post_cache')->once()->with(1);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT ...');
        $wpdb->shouldReceive('get_results')->once()->andReturn([
            (object) [
                'ID'           => 1,
                'post_title'   => "كتاب",
                'post_content' => "<p>كتاب</p>",
                'post_excerpt' => '',
            ],
        ]);
        $wpdb->shouldReceive('update')->once()->with(
            'wp_posts',
            Mockery::type('array'),
            ['ID' => 1],
            ['%s', '%s', '%s'],
            ['%d']
        );

        $GLOBALS['wpdb'] = $wpdb;

        $migrator = $this->makeMigrator();
        $result = $migrator->processBatch(['post'], 100);

        $this->assertInstanceOf(BatchResult::class, $result);
        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->modified);
        $this->assertSame(1, $result->lastId);
        $this->assertFalse($result->hasMore);

        unset($GLOBALS['wpdb']);
    }

    public function test_process_batch_empty_returns_no_more(): void
    {
        Functions\expect('get_option')
            ->with('persian_kit_normalize_cursor', 0)
            ->andReturn(0);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->posts = 'wp_posts';
        $wpdb->shouldReceive('prepare')->once()->andReturn('SELECT ...');
        $wpdb->shouldReceive('get_results')->once()->andReturn([]);

        $GLOBALS['wpdb'] = $wpdb;

        $migrator = $this->makeMigrator();
        $result = $migrator->processBatch(['post'], 100);

        $this->assertSame(0, $result->processed);
        $this->assertSame(0, $result->modified);
        $this->assertFalse($result->hasMore);

        unset($GLOBALS['wpdb']);
    }
}
