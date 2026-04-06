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
}
