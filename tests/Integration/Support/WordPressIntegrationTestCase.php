<?php

namespace PersianKit\Tests\Integration\Support;

if (class_exists('WP_UnitTestCase')) {
    abstract class WordPressIntegrationTestCase extends \WP_UnitTestCase
    {
        /**
         * Skips a test of a WooCommerce feature newer than the oldest
         * WooCommerce the plugin supports.
         */
        protected function requireWooCommerce(string $version): void
        {
            if (defined('WC_VERSION') && version_compare(WC_VERSION, $version, '<')) {
                $this->markTestSkipped("Needs WooCommerce {$version} or newer (this run has " . WC_VERSION . ').');
            }
        }

        /**
         * WooCommerce adds its email hooks when the mailer is first made, and
         * the test case removes hooks added during a test: make a new one.
         */
        protected static function newMailer(): void
        {
            // $_instance in WooCommerce 9.9.
            $property = property_exists(\WC_Emails::class, 'instance') ? 'instance' : '_instance';
            (new \ReflectionProperty(\WC_Emails::class, $property))->setValue(null, null);
            WC()->mailer();
        }
    }
} else {
    abstract class WordPressIntegrationTestCase extends \PHPUnit\Framework\TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $this->markTestSkipped('WordPress integration tests require wordpress-tests-lib.');
        }
    }
}
