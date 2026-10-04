<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Forms\AcfModule;
use PersianKit\Modules\Forms\Cf7Module;
use PersianKit\Modules\Forms\ForminatorModule;
use PersianKit\Modules\Forms\GravityFormsModule;
use PersianKit\Modules\Forms\WPFormsModule;
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
        delete_option('persian_kit_show_welcome');

        // InstallManager runs on plugins_loaded, before SettingsRegistrar
        // registers the setting on init, so its writes and the settings older
        // versions stored are not sanitized. WordPress restores hooks after
        // each test.
        remove_all_filters('sanitize_option_persian_kit_settings');
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
        $this->assertSame(Cf7Module::defaults(), $settings[Cf7Module::key()]);
        $this->assertSame(AcfModule::defaults(), $settings[AcfModule::key()]);
        $this->assertSame(ForminatorModule::defaults(), $settings[ForminatorModule::key()]);
        $this->assertSame(GravityFormsModule::defaults(), $settings[GravityFormsModule::key()]);
        $this->assertSame(WPFormsModule::defaults(), $settings[WPFormsModule::key()]);
        $this->assertArrayNotHasKey('forms', $settings);
    }

    public function test_activate_records_the_schema_version(): void
    {
        Bootstrap::activate(false);

        $this->assertSame(InstallManager::DB_VERSION, (int) get_option('persian_kit_db_version'));
    }

    public function test_fresh_activation_uses_the_safer_defaults_and_shows_the_welcome(): void
    {
        Bootstrap::activate(false);

        $settings = get_option('persian_kit_settings');
        $this->assertFalse($settings['digit_conversion']['enabled']);
        $this->assertFalse($settings['char_normalization']['normalize_on_save']);
        $this->assertTrue($settings['char_normalization']['enabled']);
        $this->assertSame('1', (string) get_option('persian_kit_show_welcome'));
    }

    public function test_upgrade_keeps_the_behaviour_of_sites_set_up_before_version_2(): void
    {
        // Saved by 1.0.0-beta.2: digit conversion and normalization were on by default.
        update_option('persian_kit_settings', [
            'char_normalization' => ['enabled' => true, 'teh_marbuta' => false],
            'utilities'          => ['enabled' => true],
        ]);

        InstallManager::maybeUpgrade();

        $settings = get_option('persian_kit_settings');
        $this->assertSame(InstallManager::DB_VERSION, (int) get_option('persian_kit_db_version'));
        $this->assertSame(
            ['enabled' => true, 'dates' => false, 'numbers' => false, 'prices' => false],
            $settings['digit_conversion']
        );
        $this->assertTrue($settings['char_normalization']['normalize_on_save']);
        $this->assertArrayNotHasKey(DateConversionModule::key(), $settings);
        $this->assertFalse(get_option('persian_kit_show_welcome'));
    }

    public function test_upgrade_keeps_a_module_the_user_turned_off(): void
    {
        update_option('persian_kit_settings', ['digit_conversion' => ['enabled' => false]]);
        update_option('persian_kit_db_version', 1);

        InstallManager::maybeUpgrade();

        $this->assertFalse(get_option('persian_kit_settings')['digit_conversion']['enabled']);
    }

    public function test_reactivation_does_not_reset_settings(): void
    {
        Bootstrap::activate(false);
        update_option('persian_kit_settings', ['digit_conversion' => ['enabled' => true]] + get_option('persian_kit_settings'));
        delete_option('persian_kit_show_welcome');

        Bootstrap::activate(false);

        $this->assertTrue(get_option('persian_kit_settings')['digit_conversion']['enabled']);
        $this->assertFalse(get_option('persian_kit_show_welcome'));
    }

    public function test_upgrade_writes_defaults_when_activation_never_ran(): void
    {
        InstallManager::maybeUpgrade();

        $settings = get_option('persian_kit_settings', []);
        $this->assertSame(UtilitiesModule::defaults(), $settings[UtilitiesModule::key()]);
    }

    /**
     * @return array<string, array{array<string, bool>, bool, bool}>
     */
    public static function formsSettings(): array
    {
        return [
            'both on'              => [['enabled' => true, 'cf7' => true, 'acf' => true], true, true],
            'CF7 off'              => [['enabled' => true, 'cf7' => false, 'acf' => true], false, true],
            'ACF off'              => [['enabled' => true, 'cf7' => true, 'acf' => false], true, false],
            'module off'           => [['enabled' => false, 'cf7' => true, 'acf' => true], false, false],
            'options never saved'  => [['enabled' => true], true, true],
        ];
    }

    /**
     * @param array<string, bool> $forms
     * @dataProvider formsSettings
     */
    public function test_upgrade_to_version_3_splits_the_forms_module(array $forms, bool $cf7, bool $acf): void
    {
        update_option('persian_kit_settings', [
            'forms'     => $forms,
            'utilities' => ['enabled' => true, 'persian_slugs' => false],
        ]);
        update_option('persian_kit_db_version', 2);

        InstallManager::maybeUpgrade();

        $settings = get_option('persian_kit_settings');
        $this->assertSame(['enabled' => $cf7], $settings['cf7']);
        $this->assertSame(['enabled' => $acf], $settings['acf']);
        $this->assertArrayNotHasKey('forms', $settings);
        $this->assertSame(['enabled' => true, 'persian_slugs' => false], $settings['utilities']);
        $this->assertSame(3, (int) get_option('persian_kit_db_version'));
    }

    public function test_upgrade_to_version_3_leaves_a_site_without_forms_settings_alone(): void
    {
        update_option('persian_kit_settings', ['utilities' => ['enabled' => true]]);
        update_option('persian_kit_db_version', 2);

        InstallManager::maybeUpgrade();

        $this->assertSame(['utilities' => ['enabled' => true]], get_option('persian_kit_settings'));
    }
}
