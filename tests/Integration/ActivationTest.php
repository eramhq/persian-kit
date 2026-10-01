<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;
use PersianKit\Service\Installation\InstallManager;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class ActivationTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        delete_option('persian_kit_settings');
        delete_option('persian_kit_db_version');
    }

    public function test_activate_sets_module_defaults(): void
    {
        Bootstrap::activate(false);

        $settings = get_option('persian_kit_settings', []);

        $this->assertSame(DigitConversionModule::defaults(), $settings[DigitConversionModule::key()]);
        $this->assertSame(DateConversionModule::defaults(), $settings[DateConversionModule::key()]);
        $this->assertSame(CharNormalizationModule::defaults(), $settings[CharNormalizationModule::key()]);
        $this->assertSame(AdminFontModule::defaults(), $settings[AdminFontModule::key()]);
        $this->assertSame(ZWNJEditorModule::defaults(), $settings[ZWNJEditorModule::key()]);
        $this->assertSame(WooCommerceModule::defaults(), $settings[WooCommerceModule::key()]);
        $this->assertSame(UtilitiesModule::defaults(), $settings[UtilitiesModule::key()]);
    }

    public function test_activate_records_the_schema_version(): void
    {
        Bootstrap::activate(false);

        $this->assertSame(InstallManager::DB_VERSION, (int) get_option('persian_kit_db_version'));
    }

    public function test_upgrade_keeps_settings_saved_before_versioning(): void
    {
        $stored = ['digit_conversion' => ['enabled' => false]];
        update_option('persian_kit_settings', $stored);

        InstallManager::maybeUpgrade();

        $this->assertSame(InstallManager::DB_VERSION, (int) get_option('persian_kit_db_version'));
        $this->assertFalse(get_option('persian_kit_settings')['digit_conversion']['enabled']);
        $this->assertArrayNotHasKey(DateConversionModule::key(), get_option('persian_kit_settings'));
    }

    public function test_upgrade_writes_defaults_when_activation_never_ran(): void
    {
        InstallManager::maybeUpgrade();

        $settings = get_option('persian_kit_settings', []);
        $this->assertSame(UtilitiesModule::defaults(), $settings[UtilitiesModule::key()]);
    }
}
