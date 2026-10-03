<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\ImportException;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\ImportState;
use PersianKit\Service\Import\SourceRegistry;
use PersianKit\Service\Import\SourceState;
use PersianKit\Tests\Integration\Support\FakeImportSource;
use PersianKit\Tests\Integration\Support\FakeImportTask;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class ImportRunnerTest extends WordPressIntegrationTestCase
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

        update_option('persian_kit_settings', ['digit_conversion' => ['enabled' => false, 'dates' => true, 'numbers' => true, 'prices' => true, 'emails' => false]]);
        $this->resetSettingsCache();
        update_option(FakeImportTask::OPTION, [1 => 'old-1', 2 => 'old-2', 3 => 'old-3', 4 => 'new-4', 5 => 'old-5']);
    }

    protected function tearDown(): void
    {
        Bootstrap::get(SourceRegistry::class)->reset();
        parent::tearDown();
    }

    public function test_start_waits_at_deactivate_while_the_source_is_active(): void
    {
        $this->source->active = true;

        $job = $this->runner()->start($this->source, ['rows' => ['digits']]);

        $this->assertSame(ImportJob::STEP_DEACTIVATE, $job->step);
        $this->assertSame(['digits'], $job->rows);
        $this->assertSame(['items'], $job->taskKeys);

        $this->expectException(ImportException::class);
        $this->runner()->run('test');
    }

    public function test_an_import_converts_settings_and_data_and_records_the_switch(): void
    {
        $job = $this->runner()->start($this->source, ['rows' => ['digits']]);
        $this->assertSame(ImportJob::STEP_IMPORT, $job->step);
        $this->assertSame(4, $job->tasks['items']['total']);

        $job = $this->runner()->run('test', 30.0);

        $this->assertTrue($job->isFinished());
        $this->assertSame(100, $job->progress()['percent']);
        $this->assertSame([1 => 'new-1', 2 => 'new-2', 3 => 'new-3', 4 => 'new-4', 5 => 'new-5'], get_option(FakeImportTask::OPTION));
        $this->assertTrue($this->settings()->module('digit_conversion', 'enabled'));

        $counts = Bootstrap::get(ImportLog::class)->counts([$job->runId]);
        $this->assertSame(5, $counts[ImportLog::CHANGED]);
        // The setting with no match is reported.
        $this->assertSame(1, $counts[ImportLog::NOT_IMPORTED]);

        $this->assertSame(SourceState::Imported, Bootstrap::get(ImportState::class)->stateOf($this->source));
        $this->assertFalse(get_option(ImportRunner::LOCK_OPTION));
    }

    public function test_reactivating_the_source_pauses_the_job(): void
    {
        $this->runner()->start($this->source);
        $this->source->active = true;

        $job = $this->runner()->run('test', 30.0);

        $this->assertSame(ImportJob::PAUSED, $job->status);
        $this->assertNotSame('', $job->pausedReason);
        $this->assertSame('old-1', get_option(FakeImportTask::OPTION)[1]);

        $this->source->active = false;
        $this->assertTrue($this->runner()->run('test', 30.0)->isFinished());
    }

    public function test_a_spent_time_budget_saves_progress_and_a_later_run_resumes(): void
    {
        $this->runner()->start($this->source, ['rows' => []]);

        // Out of time before the first batch.
        $job = $this->runner()->run('test', 0.0);
        $this->assertFalse($job->isFinished());

        // As after a reload: the saved job carries on from its cursor.
        $saved = ImportJob::load();
        $this->assertNotNull($saved);
        $this->assertSame(ImportJob::RUNNING, $saved->status);

        $job = $this->runner()->run('test', 30.0);
        $this->assertTrue($job->isFinished());
        $this->assertSame(4, $job->tasks['items']['processed']);
    }

    public function test_another_tab_or_wp_cli_holds_the_lock(): void
    {
        $this->runner()->start($this->source);
        add_option(ImportRunner::LOCK_OPTION, ['owner' => 'cli', 'expires' => time() + 60], '', false);

        try {
            $this->runner()->run('rest:1:tab');
            $this->fail('The second owner ran.');
        } catch (ImportException $error) {
            $this->assertSame(ImportException::LOCKED, $error->errorCode);
            $this->assertSame(409, $error->status);
        }

        // An expired lock is taken over.
        update_option(ImportRunner::LOCK_OPTION, ['owner' => 'cli', 'expires' => time() - 1], false);
        $this->assertTrue($this->runner()->run('rest:1:tab', 30.0)->isFinished());
    }

    public function test_one_switch_at_a_time(): void
    {
        $this->runner()->start($this->source);
        $other = new class extends FakeImportSource {
            public function key(): string
            {
                return 'other-source';
            }
        };

        $this->expectException(ImportException::class);
        $this->runner()->start($other);
    }

    public function test_a_second_run_converts_only_what_is_new(): void
    {
        $this->runner()->start($this->source, ['rows' => []]);
        $first = $this->runner()->run('test', 30.0);

        $items = get_option(FakeImportTask::OPTION);
        $items[6] = 'old-6';
        update_option(FakeImportTask::OPTION, $items);

        $second = $this->runner()->start($this->source, ['rows' => []]);
        $this->assertNotSame($first->runId, $second->runId);
        $this->assertSame(1, $second->tasks['items']['total']);

        $second = $this->runner()->run('test', 30.0);
        $this->assertSame(1, Bootstrap::get(ImportLog::class)->counts([$second->runId])[ImportLog::CHANGED]);
    }

    public function test_one_failing_item_is_logged_and_the_rest_convert(): void
    {
        $this->source->tasks[0]->failing = [2];
        $this->runner()->start($this->source, ['rows' => []]);

        $job = $this->runner()->run('test', 30.0);

        $this->assertTrue($job->isFinished());
        $items = get_option(FakeImportTask::OPTION);
        $this->assertSame('old-2', $items[2]);
        $this->assertSame('new-3', $items[3]);
        $this->assertSame(1, Bootstrap::get(ImportLog::class)->counts([$job->runId])[ImportLog::ATTENTION]);
    }

    public function test_undo_puts_back_only_what_still_holds_the_imported_value(): void
    {
        $this->runner()->start($this->source, ['rows' => ['digits']]);
        $this->runner()->run('test', 30.0);

        // Changed by hand after the import.
        $items = get_option(FakeImportTask::OPTION);
        $items[3] = 'edited';
        update_option(FakeImportTask::OPTION, $items);

        $result = $this->runner()->undo($this->source, 'test', 0, '', 30.0);

        $this->assertTrue($result['done']);
        $this->assertSame(4, $result['restored']);
        $this->assertSame(1, $result['kept']);
        $this->assertSame([1 => 'old-1', 2 => 'old-2', 3 => 'edited', 4 => 'new-4', 5 => 'old-5'], get_option(FakeImportTask::OPTION));
        $this->resetSettingsCache();
        $this->assertFalse($this->settings()->module('digit_conversion', 'enabled'));

        // Nothing left to undo, and the next import starts afresh.
        $this->assertFalse(Bootstrap::get(ImportLog::class)->hasChanges($this->source->key()));
        $this->assertSame(SourceState::InactiveWithData, Bootstrap::get(ImportState::class)->stateOf($this->source));
    }

    public function test_a_rerun_keeps_the_first_original_value_for_undo(): void
    {
        $this->runner()->start($this->source, ['rows' => []]);
        $this->runner()->run('test', 30.0);

        // The source was reactivated and wrote an old value again.
        $items = get_option(FakeImportTask::OPTION);
        $items[1] = 'old-again';
        update_option(FakeImportTask::OPTION, $items);
        $this->runner()->start($this->source, ['rows' => []]);
        $this->runner()->run('test', 30.0);

        $this->runner()->undo($this->source, 'test', 0, '', 30.0);

        $this->assertSame('old-1', get_option(FakeImportTask::OPTION)[1]);
    }

    private function runner(): ImportRunner
    {
        return Bootstrap::get(ImportRunner::class);
    }

    private function settings(): SettingsManager
    {
        return Bootstrap::get(SettingsManager::class);
    }

    private function resetSettingsCache(): void
    {
        (fn () => $this->cache = null)->call($this->settings());
    }
}
