<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportState;
use PersianKit\Service\Import\SourceState;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Service\Import\Sources\PersianWooCommerce\PersianWooCommerceSource;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Each source is found from what it left in the database, so it shows up
 * after the plugin is deactivated or deleted.
 */
class SourceDetectionTest extends WordPressIntegrationTestCase
{
    public function test_no_data_no_source(): void
    {
        $this->assertSame([], $this->listed());
        $this->assertSame(SourceState::NoData, Bootstrap::get(ImportState::class)->stateOf(new ParsiDateSource()));
    }

    public function test_parsi_date_from_its_6x_or_5x_settings_or_its_widgets(): void
    {
        update_option('wp_parsidate', ['persian_date' => true]);
        $this->assertSame(['wp-parsidate'], $this->listed());
        delete_option('wp_parsidate');

        update_option('wpp_settings', ['persian_date' => 'enable']);
        $this->assertTrue((new ParsiDateSource())->hasData());
        delete_option('wpp_settings');

        update_option('widget_parsidate_archive', [2 => ['parsidate_archive_title' => 'Archive'], '_multiwidget' => 1]);
        $this->assertTrue((new ParsiDateSource())->hasData());
    }

    public function test_persian_woocommerce_from_its_options(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes']);

        $this->assertSame(['persian-woocommerce'], $this->listed());
        $this->assertSame('inactive', Bootstrap::get(ImportReview::class)->sources()[0]['state']);
    }

    public function test_the_shipping_plugin_from_its_terms_while_the_taxonomy_is_unregistered(): void
    {
        register_taxonomy(PwsSource::TAXONOMY, null, ['hierarchical' => true]);
        wp_insert_term('تهران', PwsSource::TAXONOMY, ['slug' => 'TE']);
        unregister_taxonomy(PwsSource::TAXONOMY);

        $this->assertTrue((new PwsSource())->hasData());
    }

    public function test_imported_and_partly_imported(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes']);
        $state = Bootstrap::get(ImportState::class);
        $source = new PersianWooCommerceSource();

        $state->record($source->key(), 'run-1', 2, 3);
        $this->assertSame(SourceState::PartlyImported, $state->stateOf($source));
        $this->assertStringContainsString('2', Bootstrap::get(ImportReview::class)->summary($source)['state_label']);

        $state->record($source->key(), 'run-2', 3, 3);
        $this->assertSame(SourceState::Imported, $state->stateOf($source));
        $this->assertSame(['run-1', 'run-2'], $state->get($source->key())['run_ids']);
    }

    public function test_the_deactivate_link_is_wordpress_own_and_unescaped(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        if (is_multisite()) {
            grant_super_admin(get_current_user_id());
        }

        $url = (new ParsiDateSource())->deactivateUrl();

        $this->assertIsString($url);
        $this->assertStringNotContainsString('&amp;', $url);
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('deactivate', $query['action']);
        $this->assertSame('wp-parsidate/wp-parsidate.php', $query['plugin']);
        $this->assertSame(1, wp_verify_nonce($query['_wpnonce'], 'deactivate-plugin_wp-parsidate/wp-parsidate.php'));

        // An editor can't deactivate plugins.
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertNull((new ParsiDateSource())->deactivateUrl());
    }

    /**
     * @return list<string>
     */
    private function listed(): array
    {
        return array_column(Bootstrap::get(ImportReview::class)->sources(), 'key');
    }
}
