<?php

namespace {
    if (!class_exists('WP_Query')) {
        class WP_Query
        {
            private array $vars;

            public function __construct(array $vars = [], private bool $mainQuery = true)
            {
                $this->vars = $vars;
            }

            public function is_main_query(): bool
            {
                return $this->mainQuery;
            }

            public function get(string $key): mixed
            {
                return $this->vars[$key] ?? null;
            }

            public function set(string $key, mixed $value): void
            {
                $this->vars[$key] = $value;
            }
        }
    }
}

namespace PersianKit\Tests\Unit\DateConversion {

    use Brain\Monkey;
    use Brain\Monkey\Functions;
    use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
    use PHPUnit\Framework\TestCase;

    class PostTypeMonthFilterTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            Monkey\setUp();

            Functions\when('sanitize_text_field')->returnArg();
            Functions\when('sanitize_key')->returnArg();
            Functions\when('wp_unslash')->returnArg();
            Functions\when('is_admin')->justReturn(true);
            Functions\when('post_type_exists')->alias(function (string $postType): bool {
                return in_array($postType, ['post', 'page', 'book'], true);
            });
            Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
            Functions\when('get_post_type_object')->alias(function () {
                return (object) [
                    'labels' => (object) [
                        'filter_by_date' => 'Filter by date',
                    ],
                ];
            });
            Functions\when('esc_html')->returnArg();
            Functions\when('esc_attr')->returnArg();
            Functions\when('esc_html__')->alias(fn (string $text, ?string $domain = null): string => $text);
            Functions\when('__')->alias(fn (string $text, ?string $domain = null): string => $text);
            Functions\when('selected')->alias(function (mixed $selected, mixed $current, bool $display = false): string {
                return (string) $selected === (string) $current ? 'selected="selected"' : '';
            });
        }

        protected function tearDown(): void
        {
            unset($_GET['persian_kit_jalali_month'], $_GET['post_status']);
            unset($GLOBALS['pagenow']);

            Monkey\tearDown();
            parent::tearDown();
        }

        public function test_register_adds_hooks(): void
        {
            $filter = new PostTypeMonthFilter();
            $filter->register();

            $this->assertTrue(has_filter('pre_months_dropdown_query'));
            $this->assertTrue(has_action('restrict_manage_posts'));
            $this->assertTrue(has_action('pre_get_posts'));
        }

        public function test_suppress_core_dropdown_replaces_supported_post_type_months(): void
        {
            $filter = new PostTypeMonthFilter();

            $this->assertSame([], $filter->suppressCoreDropdown(false, 'post'));
            $this->assertSame('keep', $filter->suppressCoreDropdown('keep', 'attachment'));
        }

        public function test_month_options_build_unique_jalali_months_from_post_days(): void
        {
            $filter = new class() extends PostTypeMonthFilter {
                protected function queryDistinctPostDays(string $postType): array
                {
                    return [
                        '2026-04-20',
                        '2026-03-25',
                        '2026-03-21',
                        '2026-03-19',
                    ];
                }
            };

            $this->assertSame([
                [
                    'value' => '140501',
                    'label' => 'فروردین ۱۴۰۵',
                ],
                [
                    'value' => '140412',
                    'label' => 'اسفند ۱۴۰۴',
                ],
            ], $filter->monthOptions('post'));
        }

        public function test_selected_gregorian_range_accepts_persian_digits(): void
        {
            $_GET['persian_kit_jalali_month'] = '۱۴۰۵۰۱';

            $filter = new PostTypeMonthFilter();

            $this->assertSame([
                'start' => '2026-03-21',
                'end' => '2026-04-20',
            ], $filter->selectedGregorianRange());
        }

        public function test_filter_posts_query_appends_date_query_for_selected_jalali_month(): void
        {
            $_GET['persian_kit_jalali_month'] = '140501';
            $GLOBALS['pagenow'] = 'edit.php';

            $query = new \WP_Query([
                'post_type' => 'page',
                'date_query' => [
                    [
                        'column' => 'post_modified',
                    ],
                ],
            ]);

            $filter = new PostTypeMonthFilter();
            $filter->filterPostsQuery($query);

            $this->assertSame('', $query->get('m'));
            $this->assertSame([
                [
                    'column' => 'post_modified',
                ],
                [
                    'after' => '2026-03-21',
                    'before' => '2026-04-20',
                    'inclusive' => true,
                ],
            ], $query->get('date_query'));
        }

        public function test_render_filter_outputs_jalali_dropdown_markup(): void
        {
            $_GET['persian_kit_jalali_month'] = '140501';

            $filter = new class() extends PostTypeMonthFilter {
                public function monthOptions(string $postType): array
                {
                    return [
                        [
                            'value' => '140501',
                            'label' => 'فروردین ۱۴۰۵',
                        ],
                    ];
                }
            };

            ob_start();
            $filter->renderFilter('post', 'top');
            $output = (string) ob_get_clean();

            $this->assertStringContainsString('name="persian_kit_jalali_month"', $output);
            $this->assertStringContainsString('All dates', $output);
            $this->assertStringContainsString('فروردین ۱۴۰۵', $output);
            $this->assertStringContainsString('selected="selected"', $output);
        }
    }
}
