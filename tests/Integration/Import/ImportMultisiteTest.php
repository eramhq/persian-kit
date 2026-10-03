<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Bootstrap;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportRunner;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;
use PersianKit\Service\Import\Tasks\CustomerAddressTask;
use PersianKit\Tests\Integration\Support\UsesImportLog;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * On a network the switch runs per site: only the customers of the site
 * switching are converted, though users are shared. Runs with WP_MULTISITE=1.
 */
class ImportMultisiteTest extends WordPressIntegrationTestCase
{
    use UsesImportLog;

    protected function setUp(): void
    {
        parent::setUp();

        if (!is_multisite()) {
            $this->markTestSkipped('Runs on a network: WP_MULTISITE=1.');
        }
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $this->setUpImportLog();
        update_option('persian_kit_settings', []);
    }

    public function test_only_this_sites_customers_are_converted(): void
    {
        register_taxonomy(PwsSource::TAXONOMY, null, ['hierarchical' => true]);
        $tehran = (int) wp_insert_term('تهران', PwsSource::TAXONOMY, ['slug' => 'te'])['term_id'];
        // The city tells the plugin's own ids from Tapin's.
        $city = (int) wp_insert_term('تهران', PwsSource::TAXONOMY, ['slug' => 'تهران-شهر', 'parent' => $tehran])['term_id'];
        unregister_taxonomy(PwsSource::TAXONOMY);

        $here = self::factory()->user->create(['role' => 'customer']);
        $elsewhere = self::factory()->user->create(['role' => 'customer']);
        $otherSite = self::factory()->blog->create();
        add_user_to_blog($otherSite, $elsewhere, 'customer');
        remove_user_from_blog($elsewhere, get_current_blog_id());
        foreach ([$here, $elsewhere] as $user) {
            update_user_meta($user, 'billing_country', 'IR');
            update_user_meta($user, 'billing_state', (string) $tehran);
            update_user_meta($user, 'billing_city', (string) $city);
        }

        $runner = Bootstrap::get(ImportRunner::class);
        $job = $runner->start(new PwsSource(), ['rows' => []]);
        $task = new CustomerAddressTask();
        $this->assertSame(1, $task->count($runner->context($job)));
        // Review warns that the user table is shared with the other sites.
        $this->assertNotEmpty($task->reviewOptions($runner->context($job))['notice']);
        $this->assertTrue($runner->run('test', 30.0)->isFinished());

        $this->assertSame('THR', get_user_meta($here, 'billing_state', true));
        $this->assertSame('تهران', get_user_meta($here, 'billing_city', true));
        $this->assertSame((string) $tehran, get_user_meta($elsewhere, 'billing_state', true));
        $this->assertSame((string) $city, get_user_meta($elsewhere, 'billing_city', true));
    }

    public function test_each_site_keeps_its_own_switch(): void
    {
        update_option('PW_Options', ['persian_price' => 'yes']);
        $otherSite = self::factory()->blog->create();

        $this->assertContains('persian-woocommerce', array_column(Bootstrap::get(ImportReview::class)->sources(), 'key'));

        // The other site never used it, so it has nothing to switch.
        switch_to_blog($otherSite);
        $this->assertNotContains('persian-woocommerce', array_column(Bootstrap::get(ImportReview::class)->sources(), 'key'));
        restore_current_blog();
    }

    public function test_an_admin_without_network_rights_gets_no_link_for_a_network_active_plugin(): void
    {
        $file = 'wp-parsidate/wp-parsidate.php';
        update_site_option('active_sitewide_plugins', [$file => time()] + (array) get_site_option('active_sitewide_plugins', []));

        $admin = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin);
        $source = new \PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource();
        $this->assertTrue($source->isNetworkActive());
        $this->assertNull($source->deactivateUrl());
        $this->assertStringContainsString('network admin', Bootstrap::get(ImportReview::class)->summary($source)['deactivate']['message']);

        grant_super_admin($admin);
        $url = (string) $source->deactivateUrl();
        $this->assertStringContainsString('/wp-admin/network/plugins.php', $url);
    }
}
