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

    public function test_module_merges_registered_defaults_under_stored_values(): void
    {
        Functions\when('get_option')->justReturn([
            'utilities' => ['enabled' => false],
        ]);

        $manager = new SettingsManager();
        $manager->registerDefaults('utilities', ['enabled' => true, 'persian_slugs' => true]);
        $manager->registerDefaults('woocommerce', ['enabled' => true]);

        $this->assertSame(['enabled' => false, 'persian_slugs' => true], $manager->module('utilities'));
        $this->assertTrue($manager->module('utilities', 'persian_slugs'));
        $this->assertSame([
            'utilities'   => ['enabled' => false, 'persian_slugs' => true],
            'woocommerce' => ['enabled' => true],
        ], $manager->all());
    }

    public function test_corrupt_module_entries_are_ignored(): void
    {
        Functions\when('get_option')->justReturn(['utilities' => 'yes']);

        $manager = new SettingsManager();
        $manager->registerDefaults('utilities', ['enabled' => true]);

        $this->assertSame(['enabled' => true], $manager->module('utilities'));
    }

    public function test_update_modules_writes_once(): void
    {
        Functions\when('get_option')->justReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('persian_kit_settings', [
                'utilities'   => ['enabled' => true],
                'woocommerce' => ['enabled' => false],
            ], true);

        $manager = new SettingsManager();
        $manager->updateModules([
            'utilities'   => ['enabled' => true],
            'woocommerce' => ['enabled' => false],
        ]);

        $this->assertSame(['enabled' => false], $manager->module('woocommerce'));
    }
}
