<?php

namespace PersianKit\Tests\Integration\Support;

if (class_exists('WP_UnitTestCase')) {
    abstract class WordPressIntegrationTestCase extends \WP_UnitTestCase
    {
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
