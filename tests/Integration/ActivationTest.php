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
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class ActivationTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        delete_option('persian_kit_settings');
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
}
