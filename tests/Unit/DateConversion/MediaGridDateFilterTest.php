<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\MediaGridDateFilter;
use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
use PHPUnit\Framework\TestCase;

class MediaGridDateFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
    }

    protected function tearDown(): void
    {
        unset($_REQUEST['query'], $_REQUEST[PostTypeMonthFilter::QUERY_VAR]);
        unset($GLOBALS['pagenow']);

        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_upload_grid_hooks(): void
    {
        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $filter->register();

        $this->assertTrue(has_action('admin_enqueue_scripts'));
        $this->assertTrue(has_filter('media_library_months_with_files'));
        $this->assertTrue(has_filter('media_view_settings'));
        $this->assertTrue(has_filter('ajax_query_attachments_args'));
    }

    public function test_enqueue_loads_media_grid_script_on_upload_screen(): void
    {
        $enqueueCalls = [];

        Functions\when('wp_enqueue_script')->alias(function (...$args) use (&$enqueueCalls) {
            $enqueueCalls[] = $args;
        });

        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $filter->enqueue('upload.php');

        $this->assertSame([[
            'persian-kit-media-grid-date-filter',
            PERSIAN_KIT_URL . 'public/js/media-grid-date-filter.js',
            [],
            PERSIAN_KIT_VERSION,
            true,
        ]], $enqueueCalls);
    }

    public function test_enqueue_skips_other_admin_pages(): void
    {
        Functions\expect('wp_enqueue_script')->never();

        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $filter->enqueue('edit.php');

        $this->assertTrue(true);
    }

    public function test_suppress_core_months_only_on_upload_screen(): void
    {
        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $GLOBALS['pagenow'] = 'upload.php';

        $this->assertSame([], $filter->suppressCoreMonths(null));

        $GLOBALS['pagenow'] = 'post.php';

        $this->assertSame('keep', $filter->suppressCoreMonths('keep'));
    }

    public function test_filter_media_view_settings_replaces_upload_screen_months_with_jalali_options(): void
    {
        $filter = new MediaGridDateFilter($this->makeMonthFilter([
            [
                'value' => '140501',
                'label' => 'فروردین ۱۴۰۵',
            ],
            [
                'value' => '140412',
                'label' => 'اسفند ۱۴۰۴',
            ],
        ]));
        $GLOBALS['pagenow'] = 'upload.php';

        $settings = $filter->filterMediaViewSettings([
            'months' => [
                (object) [
                    'year' => 2026,
                    'month' => 3,
                    'text' => 'March 2026',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'text' => 'فروردین ۱۴۰۵',
                'jalaliYearMonth' => '140501',
            ],
            [
                'text' => 'اسفند ۱۴۰۴',
                'jalaliYearMonth' => '140412',
            ],
        ], $settings['months']);
    }

    public function test_filter_media_view_settings_leaves_other_screens_unchanged(): void
    {
        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $GLOBALS['pagenow'] = 'post.php';

        $settings = [
            'months' => [
                [
                    'text' => 'March 2026',
                ],
            ],
        ];

        $this->assertSame($settings, $filter->filterMediaViewSettings($settings));
    }

    public function test_filter_attachment_query_args_appends_jalali_range_from_ajax_request(): void
    {
        $_REQUEST['query'] = [
            PostTypeMonthFilter::QUERY_VAR => '۱۴۰۵۰۱',
        ];

        $filter = new MediaGridDateFilter($this->makeMonthFilter([], [
            '140501' => [
                'start' => '2026-03-21',
                'end' => '2026-04-20',
            ],
        ]));

        $query = $filter->filterAttachmentQueryArgs([
            'year' => 2026,
            'monthnum' => 3,
            'date_query' => [
                [
                    'column' => 'post_modified',
                ],
            ],
        ]);

        $this->assertSame([
            [
                'column' => 'post_modified',
            ],
            [
                'after' => '2026-03-21',
                'before' => '2026-04-20',
                'inclusive' => true,
            ],
        ], $query['date_query']);
        $this->assertArrayNotHasKey('year', $query);
        $this->assertArrayNotHasKey('monthnum', $query);
    }

    public function test_filter_attachment_query_args_ignores_invalid_requests(): void
    {
        $_REQUEST['query'] = [
            PostTypeMonthFilter::QUERY_VAR => 'invalid',
        ];

        $filter = new MediaGridDateFilter($this->makeMonthFilter());
        $query = [
            'orderby' => 'date',
        ];

        $this->assertSame($query, $filter->filterAttachmentQueryArgs($query));
    }

    private function makeMonthFilter(array $options = [
        [
            'value' => '140501',
            'label' => 'فروردین ۱۴۰۵',
        ],
    ], array $ranges = [
        '140501' => [
            'start' => '2026-03-21',
            'end' => '2026-04-20',
        ],
    ]): PostTypeMonthFilter {
        return new class($options, $ranges) extends PostTypeMonthFilter {
            public function __construct(
                private readonly array $options,
                private readonly array $ranges
            ) {
            }

            public function monthOptions(string $postType): array
            {
                return $postType === 'attachment' ? $this->options : [];
            }

            public function gregorianRangeForJalaliMonth(string $jalaliYearMonth): ?array
            {
                return $this->ranges[$jalaliYearMonth] ?? null;
            }
        };
    }
}
