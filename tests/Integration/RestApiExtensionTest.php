<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class RestApiExtensionTest extends WordPressIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        do_action('rest_api_init');
    }

    public function test_post_rest_response_contains_jalali_dates(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'نمونه نوشته',
            'post_content' => 'محتوا',
            'post_status'  => 'publish',
            'post_date'    => '2025-03-21 15:30:00',
            'post_date_gmt'=> '2025-03-21 12:00:00',
        ]);

        $request = new \WP_REST_Request('GET', '/wp/v2/posts/' . $postId);
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();

        $this->assertArrayHasKey('date_jalali', $data);
        $this->assertArrayHasKey('date_modified_jalali', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $data['date_jalali']);
        $this->assertStringStartsWith('1404-01-01', $data['date_jalali']);
    }

    public function test_jalali_dates_are_filled_when_fields_leave_the_dates_out(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        $postId = self::factory()->post->create([
            'post_status'   => 'publish',
            'post_date'     => '2025-03-21 15:30:00',
            'post_date_gmt' => '2025-03-21 12:00:00',
        ]);
        $this->setModified($postId, '2025-04-21 15:30:00', '2025-04-21 12:00:00');

        foreach (['date_jalali,date_modified_jalali', 'id,date_jalali,date_modified_jalali'] as $fields) {
            $request = new \WP_REST_Request('GET', '/wp/v2/posts/' . $postId);
            $request->set_param('_fields', $fields);
            $data = rest_do_request($request)->get_data();

            $this->assertSame('1404-01-01T15:30:00', $data['date_jalali'], $fields);
            $this->assertSame('1404-02-01T15:30:00', $data['date_modified_jalali'], $fields);
        }

        // A collection, where each item is prepared in turn.
        $other = self::factory()->post->create(['post_status' => 'publish', 'post_date' => '2025-06-15 10:00:00']);
        $request = new \WP_REST_Request('GET', '/wp/v2/posts');
        $request->set_param('_fields', 'date_jalali');
        $request->set_param('include', [$postId, $other]);
        $request->set_param('orderby', 'include');

        $this->assertSame(
            ['1404-01-01T15:30:00', '1404-03-25T10:00:00'],
            array_column(rest_do_request($request)->get_data(), 'date_jalali')
        );
    }

    public function test_a_draft_uses_its_local_date(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        $postId = self::factory()->post->create(['post_status' => 'draft', 'post_date' => '2025-03-20 23:00:00']);
        $this->assertSame('0000-00-00 00:00:00', get_post($postId)->post_date_gmt);

        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $request = new \WP_REST_Request('GET', '/wp/v2/posts/' . $postId);
        $request->set_param('_fields', 'date_jalali');

        $this->assertSame('1403-12-30T23:00:00', rest_do_request($request)->get_data()['date_jalali']);
    }

    private function setModified(int $postId, string $local, string $gmt): void
    {
        global $wpdb;

        $wpdb->update($wpdb->posts, ['post_modified' => $local, 'post_modified_gmt' => $gmt], ['ID' => $postId]);
        clean_post_cache($postId);
    }
}
