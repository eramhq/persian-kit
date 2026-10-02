<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Bootstrap;
use PersianKit\Modules\DateConversion\JalaliPermalinks;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Jalali permalinks follow each post's language, whatever the page's.
 *
 * @group polylang
 */
class PolylangPermalinksTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;
    use UsesPolylang;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);
    }

    public function test_persian_posts_get_jalali_links_and_others_gregorian_ones(): void
    {
        $persian = $this->postIn('fa', ['post_date' => '2026-10-02 10:00:00', 'post_name' => 'fa-post']);
        $english = $this->postIn('en', ['post_date' => '2026-10-02 10:00:00', 'post_name' => 'en-post']);

        $this->assertStringEndsWith('/1405/07/10/fa-post/', get_permalink($persian));
        $this->assertStringEndsWith('/2026/10/02/en-post/', get_permalink($english));

        // The page's language does not change a post's link.
        $this->useLanguage('en');
        $this->assertStringEndsWith('/1405/07/10/fa-post/', get_permalink($persian));
    }

    public function test_a_post_without_a_language_follows_the_default_one(): void
    {
        $postId = self::factory()->post->create(['post_status' => 'publish', 'post_date' => '2026-10-02 10:00:00', 'post_name' => 'no-language']);
        wp_delete_object_term_relationships($postId, 'language');
        clean_object_term_cache($postId, 'post');

        $this->assertFalse(pll_get_post_language($postId));
        $this->assertStringEndsWith('/1405/07/10/no-language/', get_permalink($postId));
    }

    public function test_an_english_posts_jalali_address_redirects_to_its_gregorian_one(): void
    {
        $english = $this->postIn('en', ['post_date' => '2026-10-02 10:00:00', 'post_name' => 'en-post']);
        $permalink = get_permalink($english);
        $jalaliPath = str_replace('/2026/10/02/', '/1405/07/10/', (string) wp_parse_url($permalink, PHP_URL_PATH));

        $this->go_to(home_url($jalaliPath));
        $this->assertTrue(is_single());

        $this->assertSame($permalink, $this->permalinks()->redirectUrl($jalaliPath));
        $this->assertNull($this->permalinks()->redirectUrl((string) wp_parse_url($permalink, PHP_URL_PATH)));
    }

    private function permalinks(): JalaliPermalinks
    {
        return Bootstrap::get(JalaliPermalinks::class);
    }
}
