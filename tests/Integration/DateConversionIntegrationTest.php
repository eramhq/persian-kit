<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class DateConversionIntegrationTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($this->adminId);

        require_once ABSPATH . 'wp-admin/includes/screen.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';
        require_once ABSPATH . 'wp-admin/includes/dashboard.php';
    }

    public function test_feed_timestamps_stay_gregorian(): void
    {
        update_option('timezone_string', 'Asia/Tehran');

        self::factory()->post->create([
            'post_title'    => 'نمونه',
            'post_status'   => 'publish',
            'post_date'     => '2025-03-20 23:00:00',
            'post_date_gmt' => '2025-03-20 19:30:00',
        ]);

        $this->go_to(get_feed_link('rss2'));
        $this->assertTrue(is_feed());

        the_post();

        // Exactly what feed-rss2.php and feed-atom.php print.
        $this->assertSame(
            'Thu, 20 Mar 2025 19:30:00 +0000',
            mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false)
        );
        $this->assertSame('2025-03-20T19:30:00Z', get_post_time('Y-m-d\TH:i:s\Z', true));
        $this->assertSame('2025-03-20', get_the_date('Y-m-d'));
    }

    public function test_post_and_comment_dates_match_wp_date_in_site_timezone(): void
    {
        update_option('timezone_string', 'Asia/Tehran');

        $postId = self::factory()->post->create([
            'post_status'   => 'publish',
            'post_date'     => '2025-03-20 23:00:00',
            'post_date_gmt' => '2025-03-20 19:30:00',
        ]);
        $commentId = self::factory()->comment->create([
            'comment_post_ID'  => $postId,
            'comment_date'     => '2025-03-20 23:00:00',
            'comment_date_gmt' => '2025-03-20 19:30:00',
        ]);

        $this->assertSame('1403/12/30', get_the_date('Y/m/d', $postId));
        $this->assertSame('23:00', get_the_time('H:i', $postId));
        $this->assertSame('1403/12/30', get_comment_date('Y/m/d', $commentId));
    }

    public function test_draft_date_has_no_negative_year(): void
    {
        $draftId = self::factory()->post->create([
            'post_status'   => 'draft',
            'post_date'     => '2025-03-21 15:30:00',
            'post_date_gmt' => '0000-00-00 00:00:00',
        ]);

        $this->assertSame('1404', get_the_date('Y', $draftId));
        $this->assertMatchesRegularExpression('/^14\d\d$/', get_the_modified_date('Y', $draftId));
    }

    public function test_month_archive_title_is_jalali(): void
    {
        self::factory()->post->create([
            'post_title'    => 'نمونه',
            'post_status'   => 'publish',
            'post_date'     => '2025-03-21 15:30:00',
            'post_date_gmt' => '2025-03-21 12:00:00',
        ]);

        $this->go_to('/?m=202503');

        $this->assertTrue(is_month());

        $title = get_the_archive_title();

        $this->assertStringContainsString('اسفند 1403 – فروردین 1404', $title);
        $this->assertStringNotContainsString('2025', $title);
    }

    public function test_post_list_date_column_is_jalali(): void
    {
        global $mode;

        $post = get_post(self::factory()->post->create([
            'post_title'    => 'نمونه',
            'post_status'   => 'publish',
            'post_date'     => '2025-03-21 15:30:00',
            'post_date_gmt' => '2025-03-21 12:00:00',
            'post_type'     => 'post',
        ]));

        set_current_screen('edit-post');
        $mode = 'list';

        $table = new \WP_Posts_List_Table(['screen' => 'edit-post']);

        ob_start();
        $table->column_date($post);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('1404', $output);
        $this->assertStringNotContainsString('2025', $output);
    }

    public function test_dashboard_activity_widget_outputs_jalali_post_dates(): void
    {
        self::factory()->post->create([
            'post_title'    => 'نمونه',
            'post_status'   => 'publish',
            'post_date'     => '2025-03-21 15:30:00',
            'post_date_gmt' => '2025-03-21 12:00:00',
            'post_type'     => 'post',
        ]);

        set_current_screen('dashboard');
        wp_dashboard_setup();

        global $wp_meta_boxes;

        $widget = null;

        foreach (['high', 'core', 'default', 'low'] as $priority) {
            if (isset($wp_meta_boxes['dashboard']['normal'][$priority]['dashboard_activity'])) {
                $widget = $wp_meta_boxes['dashboard']['normal'][$priority]['dashboard_activity'];
                break;
            }
        }

        $this->assertIsArray($widget);
        $this->assertIsCallable($widget['callback']);

        ob_start();
        call_user_func($widget['callback']);
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('1404', $output);
        $this->assertStringNotContainsString('2025', $output);
    }

    public function test_post_date_block_renders_jalali_text_and_preserves_gregorian_datetime(): void
    {
        $content = '<div class="wp-block-post-date"><time datetime="2025-03-21T15:30:00+03:30"><a href="http://example.org/hello-world/">March 21, 2025</a></time></div>';
        $block = [
            'blockName' => 'core/post-date',
            'attrs'     => [
                'isLink' => true,
                'format' => 'Y/m/d',
            ],
        ];

        $output = apply_filters('render_block_core/post-date', $content, $block, null);

        $this->assertStringContainsString('datetime="2025-03-21T15:30:00+03:30"', $output);
        $this->assertStringContainsString('<a href="http://example.org/hello-world/">1404/01/01</a>', $output);
    }

    public function test_post_date_block_human_diff_output_is_unchanged(): void
    {
        $content = '<div class="wp-block-post-date"><time datetime="2025-03-21T15:30:00+03:30">2 days ago</time></div>';
        $block = [
            'blockName' => 'core/post-date',
            'attrs'     => [
                'format' => 'human-diff',
            ],
        ];

        $output = apply_filters('render_block_core/post-date', $content, $block, null);

        $this->assertSame($content, $output);
    }

    public function test_latest_comments_block_renders_jalali_text_and_preserves_gregorian_datetime(): void
    {
        $content = '<ol class="wp-block-latest-comments"><li class="wp-block-latest-comments__comment"><article><footer class="wp-block-latest-comments__comment-meta"><time datetime="2025-03-21T15:30:00+03:30" class="wp-block-latest-comments__comment-date">March 21, 2025</time></footer></article></li></ol>';

        $output = apply_filters('render_block_core/latest-comments', $content, ['blockName' => 'core/latest-comments'], null);

        $this->assertStringContainsString('datetime="2025-03-21T15:30:00+03:30"', $output);
        $this->assertStringContainsString('1404', $output);
        $this->assertStringNotContainsString('March 21, 2025', $output);
    }

    public function test_a_post_shows_the_gregorian_date_too_on_the_front_end(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        update_option('date_format', 'j F Y');
        $this->showGregorianToo();
        $postId = $this->publishedPostOnOctober2();
        $commentId = self::factory()->comment->create([
            'comment_post_ID'  => $postId,
            'comment_date'     => '2026-10-02 10:30:00',
            'comment_date_gmt' => '2026-10-02 07:00:00',
        ]);

        $this->go_to(get_permalink($postId));
        the_post();

        $this->assertSame('10 مهر 1405 (' . self::ltr('2026-10-02') . ')', get_the_date());
        $this->assertSame('10 مهر 1405 (' . self::ltr('2026-10-02') . ')', get_comment_date('', $commentId));
        $this->assertSame('10:30', get_the_time('H:i'));
        $this->assertSame('1405', get_the_date('Y'));
        // Themes ask for Y-m-d to fill a datetime attribute.
        $this->assertSame('1405-07-10', get_the_date('Y-m-d'));

        $block = (new \WP_Block(['blockName' => 'core/post-date', 'attrs' => []], ['postId' => $postId]))->render();
        $this->assertStringContainsString('<time datetime="2026-10-02T10:30:00+03:30">10 مهر 1405 (' . self::ltr('2026-10-02') . ')</time>', $block);
    }

    public function test_the_gregorian_date_can_come_first_with_month_names(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        $this->showGregorianToo('named', 'gregorian_first', 'dash');
        $postId = $this->publishedPostOnOctober2();

        $this->assertSame('2 اکتبر 2026 – 10 مهر 1405', get_the_date('j F Y', $postId));
    }

    public function test_feeds_and_rest_keep_one_date(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        $this->showGregorianToo();
        $postId = $this->publishedPostOnOctober2();

        $response = rest_do_request(new \WP_REST_Request('GET', '/wp/v2/posts/' . $postId));
        $this->assertSame('1405-07-10T10:30:00', $response->get_data()['date_jalali']);
        $this->assertSame('2026-10-02T10:30:00', $response->get_data()['date']);

        $this->go_to(get_feed_link('rss2'));
        the_post();
        $this->assertSame('October 2, 2026', get_the_date('F j, Y'));
    }

    private function publishedPostOnOctober2(): int
    {
        return self::factory()->post->create([
            'post_status'   => 'publish',
            'post_date'     => '2026-10-02 10:30:00',
            'post_date_gmt' => '2026-10-02 07:00:00',
        ]);
    }
}
