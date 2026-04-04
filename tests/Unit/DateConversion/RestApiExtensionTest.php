<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use PersianKit\Modules\DateConversion\RestApiExtension;

class RestApiExtensionTest extends TestCase
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

    public function test_register_hooks_rest_api_init(): void
    {
        $ext = new RestApiExtension();
        $ext->register();

        $this->assertTrue(has_action('rest_api_init'));
    }

    public function test_register_fields_calls_register_rest_field(): void
    {
        $registeredFields = [];

        Functions\expect('get_post_types')
            ->once()
            ->with(['show_in_rest' => true], 'names')
            ->andReturn(['post' => 'post', 'page' => 'page']);

        Functions\when('register_rest_field')->alias(function ($postType, $field) use (&$registeredFields) {
            $registeredFields[] = "{$postType}:{$field}";
        });

        $ext = new RestApiExtension();
        $ext->registerFields();

        $this->assertContains('post:date_jalali', $registeredFields);
        $this->assertContains('post:date_modified_jalali', $registeredFields);
        $this->assertContains('page:date_jalali', $registeredFields);
        $this->assertContains('page:date_modified_jalali', $registeredFields);
        $this->assertCount(4, $registeredFields);
    }

    public function test_get_date_jalali_returns_iso_format(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $ext = new RestApiExtension();

        $post = [
            'date'         => '2025-03-21T15:30:00',
            'date_gmt'     => '2025-03-21T12:00:00',
            'modified'     => '2025-03-21T15:30:00',
            'modified_gmt' => '2025-03-21T12:00:00',
        ];

        $result = $ext->getDateJalali($post);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $result);
        $this->assertStringStartsWith('1404-01-01', $result);
    }

    public function test_get_modified_date_jalali_returns_iso_format(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $ext = new RestApiExtension();

        $post = [
            'date'         => '2025-03-21T15:30:00',
            'date_gmt'     => '2025-03-21T12:00:00',
            'modified'     => '2025-03-21T15:30:00',
            'modified_gmt' => '2025-03-21T12:00:00',
        ];

        $result = $ext->getModifiedDateJalali($post);

        $this->assertStringStartsWith('1404-01-01', $result);
    }
}
