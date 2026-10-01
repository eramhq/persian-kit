<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class CharNormalizationIntegrationTest extends WordPressIntegrationTestCase
{
    public function test_saves_are_left_alone_by_default(): void
    {
        $postId = wp_insert_post([
            'post_title'   => 'كتابي',
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        $this->assertSame('كتابي', get_post($postId)->post_title);
    }

    public function test_wp_insert_post_normalizes_public_post_content_when_enabled(): void
    {
        $this->bootModuleWith(['normalize_on_save' => true]);

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

    public function test_search_with_persian_letters_finds_a_post_stored_with_arabic_letters(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'كتابي تازه',
            'post_content' => 'نمونه',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=' . rawurlencode('کتابی'));

        global $wp_query;

        $this->assertSame('کتابی', $wp_query->get('s'), 'the search terms are not rewritten');
        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_search_with_arabic_letters_finds_a_post_stored_with_persian_letters(): void
    {
        $postId = self::factory()->post->create([
            'post_title'   => 'نمونه',
            'post_content' => 'یک کتاب تازه',
            'post_status'  => 'publish',
        ]);

        $this->go_to('/?s=' . rawurlencode('كتاب'));

        global $wp_query;

        $this->assertSame([$postId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_excluded_term_excludes_both_spellings(): void
    {
        self::factory()->post->create(['post_title' => 'باغ كتاب', 'post_status' => 'publish']);
        self::factory()->post->create(['post_title' => 'باغ کتاب', 'post_status' => 'publish']);
        $keptId = self::factory()->post->create(['post_title' => 'باغ گل', 'post_status' => 'publish']);

        $this->go_to('/?s=' . rawurlencode('باغ -کتاب'));

        global $wp_query;

        $this->assertSame([$keptId], wp_list_pluck($wp_query->posts, 'ID'));
    }

    public function test_search_without_yeh_or_kaf_keeps_core_sql(): void
    {
        self::factory()->post->create(['post_title' => 'باغ گل', 'post_status' => 'publish']);

        $this->go_to('/?s=' . rawurlencode('باغ'));

        global $wp_query;

        $this->assertStringNotContainsString(' OR ' . $GLOBALS['wpdb']->posts . '.post_title LIKE', $wp_query->request);
        $this->assertCount(1, $wp_query->posts);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function bootModuleWith(array $values): void
    {
        update_option('persian_kit_settings', [
            CharNormalizationModule::key() => array_replace(CharNormalizationModule::defaults(), $values),
        ]);

        $settings = new SettingsManager();
        $settings->registerDefaults(CharNormalizationModule::key(), CharNormalizationModule::defaults());

        // Hooks added here are removed again after the test.
        (new CharNormalizationModule($settings))->boot(Bootstrap::container());
    }
}
