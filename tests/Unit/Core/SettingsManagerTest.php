<?php

namespace PersianKit\Tests\Unit\Core;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Core\SettingsManager;
use PHPUnit\Framework\TestCase;

class SettingsManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_update_module_replaces_existing_settings(): void
    {
        $existing = [
            'date_conversion' => [
                'enabled'           => true,
                'global_conversion' => true,
            ],
            'admin_font' => [
                'enabled' => true,
                'font'    => 'vazirmatn',
            ],
        ];

        Functions\expect('get_option')
            ->once()
            ->with('persian_kit_settings', [])
            ->andReturn($existing);

        Functions\expect('update_option')
            ->once()
            ->with('persian_kit_settings', [
                'date_conversion' => [
                    'enabled'           => false,
                ],
                'admin_font' => [
                    'enabled' => true,
                    'font'    => 'vazirmatn',
                ],
            ], true);

        $manager = new SettingsManager();
        $manager->updateModule('date_conversion', ['enabled' => false]);

        $this->assertFalse($manager->module('date_conversion', 'enabled'));
        $this->assertNull($manager->module('date_conversion', 'global_conversion'));
    }
}
