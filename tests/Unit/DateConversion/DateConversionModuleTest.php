<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Mockery;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PHPUnit\Framework\TestCase;

class DateConversionModuleTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeModule(): DateConversionModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(function (string $key, ?string $subKey = null, mixed $default = null) {
            $defaults = DateConversionModule::defaults();

            if ($subKey === null) {
                return $defaults;
            }

            return $defaults[$subKey] ?? $default;
        });

        return new DateConversionModule($manager);
    }

    public function test_sanitize_settings_sets_missing_checkbox_to_false(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'           => true,
            'global_conversion' => false,
        ], $module->sanitizeSettings([
            'enabled' => true,
        ]));
    }

    public function test_sanitize_settings_accepts_enabled_global_conversion(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'           => true,
            'global_conversion' => true,
        ], $module->sanitizeSettings([
            'enabled'           => true,
            'global_conversion' => '1',
        ]));
    }
}
