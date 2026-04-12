<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\MediaAttachmentDateFormatter;
use PHPUnit\Framework\TestCase;

class MediaAttachmentDateFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('__')->alias(fn (string $text, ?string $domain = null): string => $text);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_attachment_js_filter(): void
    {
        $formatter = new MediaAttachmentDateFormatter();
        $formatter->register();

        $this->assertTrue(has_filter('wp_prepare_attachment_for_js'));
    }

    public function test_filter_attachment_data_replaces_date_formatted_with_jalali_text(): void
    {
        $formatter = new MediaAttachmentDateFormatter();
        $attachment = (object) [
            'post_date' => '2025-03-21 15:30:00',
        ];

        $result = $formatter->filterAttachmentData([
            'dateFormatted' => 'March 21, 2025',
        ], $attachment);

        $this->assertSame('فروردین 1, 1404', $result['dateFormatted']);
    }

    public function test_filter_attachment_data_leaves_response_unchanged_without_date(): void
    {
        $formatter = new MediaAttachmentDateFormatter();

        $this->assertSame([
            'mime' => 'image/jpeg',
        ], $formatter->filterAttachmentData([
            'mime' => 'image/jpeg',
        ], (object) [
            'post_date' => '2025-03-21 15:30:00',
        ]));
    }
}
