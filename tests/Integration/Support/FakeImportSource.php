<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Settings\SettingStatus;

/**
 * A source for the runner's tests: active or not as the test says, one
 * setting row and one task over FakeImportTask's items.
 */
class FakeImportSource extends AbstractSource
{
    public bool $active = false;

    /** @var list<\PersianKit\Service\Import\Task> */
    public array $tasks = [];

    public function __construct()
    {
        $this->tasks = [new FakeImportTask()];
    }

    public function key(): string
    {
        return 'fake-source';
    }

    public function name(): string
    {
        return 'Fake Source';
    }

    public function pluginFile(): string
    {
        return 'fake-source/fake-source.php';
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hasData(): bool
    {
        return true;
    }

    public function settingRows(SettingsManager $settings): array
    {
        return [
            new SettingRow('digits', 'Persian digits', SettingStatus::Same, 'Persian digits', ['digit_conversion.enabled' => true]),
            new SettingRow('dual', 'Dual dates', SettingStatus::NotYet, '', [], 'Not in Persian Kit yet.'),
        ];
    }

    public function tasks(): array
    {
        return $this->tasks;
    }
}
