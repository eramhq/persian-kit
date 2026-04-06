<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class CharNormalizationIntegrationTest extends WordPressIntegrationTestCase
{
    public function test_wp_insert_post_normalizes_public_post_content(): void
    {
        $postId = wp_insert_post([
            'post_title'   => 'كتابي',
            'post_content' => '<p>سلامي ١٢٣</p>',
            'post_excerpt' => 'كتاب',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $post = get_post($postId);

        $this->assertSame('کتابی', $post->post_title);
        $this->assertSame('<p>سلامی ۱۲۳</p>', $post->post_content);
        $this->assertSame('کتاب', $post->post_excerpt);
    }

    public function test_search_query_is_normalized_for_main_query(): void
    {
        wp_insert_post([
            'post_title'   => 'کتاب 123',
            'post_content' => 'نمونه',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $query = new \WP_Query([
            's'              => 'كتاب ۱۲۳',
            'post_type'      => 'post',
            'posts_per_page' => 10,
        ]);

        $this->assertSame('کتاب 123', $query->get('s'));
        $this->assertNotEmpty($query->posts);
    }
}
