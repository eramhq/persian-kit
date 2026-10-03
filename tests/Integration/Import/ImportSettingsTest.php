<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\ImportJob;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\ImportReport;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\ImportState;
use PersianKit\Service\Import\PluginsScreenNotice;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSettings;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Service\Import\Sources\PersianWooCommerce\PersianWooCommerceSource;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Each source's settings next to Persian Kit's, and importing them.
 */
class ImportSettingsTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpImportLog();
        update_option('WPLANG', 'fa_IR');
        $this->resetSettings([]);
    }

    public function test_parsi_date_6x_rows(): void
    {
        update_option('wp_parsidate', [
            'persian_date' => true, 'conv_permalinks' => '1', 'conv_title' => 'on', 'conv_contents' => true,
            'conv_dates' => 'yes', 'conv_arabic' => true, 'enable_fonts' => true, 'dual_date' => true,
            'save_options_time_123456' => 1735689600,
        ]);
        update_option('wp_parsidate_woocommerce', ['fix_prices' => true, 'validate_postcode' => true, 'mellat_gateway_enable' => true]);
        update_option('wp_parsidate_acf', ['fix_date' => true, 'save_persian_date' => true]);

        $rows = $this->byId((new ParsiDateSource())->settingRows(Bootstrap::get(SettingsManager::class)));

        $this->assertSame(['date_conversion.enabled' => true], $rows['persian_date']->changes);
        $this->assertSame(['date_conversion.jalali_permalinks' => true], $rows['conv_permalinks']->changes);
        $this->assertSame('same', $rows['conv_permalinks']->status->value);
        // Only some of the content places were on: one switch covers all, so unticked.
        $this->assertSame('close', $rows['conv_content']->status->value);
        $this->assertFalse($rows['conv_content']->ticked);
        $this->assertSame(['admin_font.enabled' => true, 'admin_font.font' => 'vazirmatn'], $rows['enable_fonts']->changes);
        // One of the two checks: close and unticked.
        $this->assertSame('close', $rows['validate_fields']->status->value);
        $this->assertFalse($rows['validate_fields']->ticked);
        $this->assertSame(['acf.enabled' => true], $rows['acf_fix_date']->changes);
        $this->assertSame('automatic', $rows['acf_save_persian_date']->status->value);
        $this->assertSame('not_yet', $rows['dual_date']->status->value);
        $this->assertStringContainsString('#14', $rows['dual_date']->reason);
        $this->assertSame('not_yet', $rows['gateway_mellat']->status->value);
        $this->assertArrayNotHasKey('edd', $rows);
    }

    public function test_parsi_date_5x_settings_are_read_as_6x(): void
    {
        update_option('wpp_settings', [
            'persian_date' => 'enable', 'conv_permalinks' => 'enable', 'conv_dates' => 'disable',
            'woo_per_price' => 'enable', 'woo_gateways' => ['mellat' => 'mellat'], 'acf_persian_date' => 'enable',
            'wpp_multilingual_support' => 'enable',
        ]);

        $settings = new ParsiDateSettings();

        $this->assertTrue($settings->on('core', 'persian_date'));
        $this->assertTrue($settings->on('core', 'multilingual_support'));
        $this->assertFalse($settings->on('core', 'conv_dates'));
        $this->assertTrue($settings->on('woocommerce', 'fix_prices'));
        $this->assertTrue($settings->on('acf', 'save_persian_date'));
        $this->assertSame(['mellat'], $settings->gateways());
        $this->assertArrayHasKey('conv_permalinks', $this->byId($settings->rows()));
    }

    public function test_on_a_site_in_another_language_parsi_dates_rows_start_unticked(): void
    {
        update_option('WPLANG', '');
        update_option('wp_parsidate', ['persian_date' => true, 'conv_dates' => true]);

        $rows = $this->byId((new ParsiDateSettings())->rows());

        $this->assertFalse($rows['persian_date']->ticked);
        $this->assertStringContainsString('did not apply', $rows['persian_date']->reason);
        $this->assertFalse($rows['conv_dates']->ticked);
    }

    public function test_persian_woocommerce_rows_read_its_defaults(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes', 'allowed_states' => 'specific', 'specific_allowed_states' => ['TE', 'ESF', 'nope'], 'admin_font_family' => 'iransans', 'enable_call_for_price' => 'yes']);

        $rows = $this->byId((new PersianWooCommerceSource())->settingRows(Bootstrap::get(SettingsManager::class)));

        // Saved as 'yes' or left at its default 'yes'.
        $this->assertArrayHasKey('enable_jalali_datepicker', $rows);
        $this->assertArrayHasKey('fix_persian_numbers', $rows);
        // The legacy two-letter code is mapped.
        $this->assertSame(['woocommerce.allowed_states' => ['ESF', 'THR']], $rows['allowed_states']->changes);
        // A commercial font can't come with Persian Kit: close, unticked.
        $this->assertFalse($rows['admin_font_family']->ticked);
        $this->assertSame('not_yet', $rows['enable_call_for_price']->status->value);
    }

    public function test_city_list_of_persian_woocommerce_is_left_to_the_shipping_plugin(): void
    {
        update_option('PW_Options', ['enable_iran_cities' => 'yes']);
        $this->assertArrayHasKey('enable_iran_cities', $this->byId((new PersianWooCommerceSource())->settingRows(Bootstrap::get(SettingsManager::class))));

        update_option('pws_install_cities', 1);
        $this->assertArrayNotHasKey('enable_iran_cities', $this->byId((new PersianWooCommerceSource())->settingRows(Bootstrap::get(SettingsManager::class))));
    }

    public function test_a_module_that_is_off_gets_a_row_to_turn_it_on(): void
    {
        $this->resetSettings(['woocommerce' => ['enabled' => false], 'digit_conversion' => ['enabled' => false]]);
        update_option('PW_Options', ['persian_price' => 'yes']);

        $rows = $this->byId($this->runner()->settingRows(new PersianWooCommerceSource()));

        $this->assertSame(['woocommerce.enabled' => true], $rows['module:woocommerce']->changes);
        $this->assertTrue($rows['module:woocommerce']->ticked);
        // Persian digits would also convert titles and content: unticked.
        $this->assertFalse($rows['module:digit_conversion']->ticked);
    }

    public function test_importing_settings_turns_on_and_logs_and_undo_puts_back(): void
    {
        $this->resetSettings(['woocommerce' => ['enabled' => true, 'checkout_normalize' => false, 'city_select' => false, 'allowed_states' => ['FRS']]]);
        update_option('PW_Options', [
            'enable_jalali_datepicker' => 'no', 'enable_iran_cities' => 'no', 'fix_postcode_persian_number' => 'yes', 'fix_phone_persian_number' => 'no',
            'phone_validation' => 'no', 'allowed_states' => 'specific', 'specific_allowed_states' => ['THR'], 'admin_font_family' => 'none',
        ]);
        $source = new PersianWooCommerceSource();

        $job = $this->runner()->start($source);
        $this->assertSame(['allowed_states', 'fix_persian_numbers'], $job->rows);
        $job = $this->runner()->run('test', 30.0);
        $this->assertTrue($job->isFinished());

        $this->resetSettingsCache();
        $woo = Bootstrap::get(SettingsManager::class)->module('woocommerce');
        $this->assertTrue($woo['checkout_normalize']);
        // Already a list: both provinces.
        $this->assertSame(['FRS', 'THR'], $woo['allowed_states']);

        $report = Bootstrap::get(ImportReport::class)->page($source, ImportLog::CHANGED, 1);
        $this->assertEqualsCanonicalizing(['woocommerce.checkout_normalize', 'woocommerce.allowed_states'], array_column($report['rows'], 'field'));
        $this->assertNotSame([], $report['tips']);

        // Changed by hand since: kept.
        $settings = Bootstrap::get(SettingsManager::class);
        $settings->updateModule('woocommerce', ['allowed_states' => ['ESF']] + $woo);

        $result = $this->runner()->undo($source, 'test', 0, '', 30.0);
        $this->assertSame(['restored' => 1, 'kept' => 1], ['restored' => $result['restored'], 'kept' => $result['kept']]);
        $this->resetSettingsCache();
        $this->assertFalse($settings->module('woocommerce', 'checkout_normalize'));
        $this->assertSame(['ESF'], $settings->module('woocommerce', 'allowed_states'));
    }

    public function test_jalali_post_links_are_kept_on_and_remembered(): void
    {
        update_option('wp_parsidate', ['conv_permalinks' => true]);

        $this->runner()->start(new ParsiDateSource());
        $this->runner()->run('test', 30.0);

        $this->resetSettingsCache();
        $this->assertTrue(Bootstrap::get(SettingsManager::class)->module('date_conversion', 'jalali_permalinks'));
        $this->assertTrue(Bootstrap::get(ImportState::class)->jalaliUrls());
    }

    public function test_the_shipping_plugin_review_offers_to_keep_it(): void
    {
        $note = (new PwsSource())->reviewNotes([])[0];

        $this->assertSame('keep_or_switch', $note['key']);
        // No zone uses its methods here.
        $this->assertFalse($note['choice']['keep']);
    }

    public function test_the_plugins_screen_links_back_to_a_waiting_switch(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        update_option('PW_Options', ['persian_price' => 'yes']);
        set_current_screen('plugins');

        $notice = new PluginsScreenNotice(Bootstrap::get(\PersianKit\Service\Import\SourceRegistry::class));
        $this->assertSame('', $this->render($notice));

        (new ImportJob('r1', PersianWooCommerceSource::KEY))->save();
        $output = $this->render($notice);
        $this->assertStringContainsString('Continue the switch from Persian WooCommerce', $output);
        $this->assertStringContainsString('tab=tools#persian-kit-switch', $output);

        set_current_screen('dashboard');
        $this->assertSame('', $this->render($notice));
    }

    public function test_the_report_downloads_as_csv_with_a_byte_order_mark(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes', 'enable_call_for_price' => 'yes']);
        $source = new PersianWooCommerceSource();
        $this->runner()->start($source);
        $this->runner()->run('test', 30.0);

        $csv = Bootstrap::get(ImportReport::class)->csv($source);

        $this->assertStringStartsWith("\xEF\xBB\xBF\"Outcome\"", $csv);
        // Persian Kit already shows Persian prices by default; the city list was turned on.
        $this->assertStringNotContainsString('"digit_conversion.prices"', $csv);
        $this->assertStringContainsString('"woocommerce.city_select","Off","On"', $csv);
        $this->assertStringContainsString('"Not imported"', $csv);
        // A value a spreadsheet would run as a formula is quoted.
        $this->assertSame("\"'=1+1\",\"a\"\"b\"\r\n", ImportReport::csvLine(['=1+1', 'a"b']));
    }

    /**
     * @param list<SettingRow> $rows
     * @return array<string, SettingRow>
     */
    private function byId(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->id] = $row;
        }

        return $byId;
    }

    private function render(PluginsScreenNotice $notice): string
    {
        ob_start();
        $notice->render();

        return trim((string) ob_get_clean());
    }

    /**
     * @param array<string, array<string, mixed>> $settings
     */
    private function resetSettings(array $settings): void
    {
        update_option('persian_kit_settings', $settings);
        $this->resetSettingsCache();
    }

    private function resetSettingsCache(): void
    {
        (fn () => $this->cache = null)->call(Bootstrap::get(SettingsManager::class));
    }

    private function runner(): ImportRunner
    {
        return Bootstrap::get(ImportRunner::class);
    }
}
