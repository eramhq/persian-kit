<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class DateConversionIntegrationTest extends WordPressIntegrationTestCase
{
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

        $this->assertStringContainsString('1404', $title);
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
}
