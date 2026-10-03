<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Persian digits outside the post content: titles, the browser tab, widgets,
 * category and tag lists, archive counts, navigation and comment excerpts.
 * Links keep their digits, and so do feeds.
 */
class DigitConversionDisplayTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    public function set_up(): void
    {
        parent::set_up();

        $this->set_permalink_structure('');
        $this->bootDateConversionWith([]);
        $this->bootDigits();
    }

    public function test_a_title_keeps_its_dashes_and_quotes(): void
    {
        $post = self::factory()->post->create(['post_title' => "Top 10 - it's"]);

        $this->assertSame('Top ۱۰ &#8211; it&#8217;s', get_the_title($post));
    }

    public function test_the_document_title_keeps_the_site_name_as_typed(): void
    {
        update_option('blogname', 'Shop24');
        $this->go_to(get_permalink(self::factory()->post->create(['post_title' => "Top 10 - it's", 'post_status' => 'publish'])));

        // Core's own title; Yoast SEO's and Rank Math's are in their own tests.
        add_filter('pre_get_document_title', '__return_empty_string', 9998);

        $this->assertSame('Top ۱۰ &#8211; it&#8217;s &#8211; Shop24', wp_get_document_title());
    }

    public function test_a_title_from_pre_get_document_title_is_converted(): void
    {
        update_option('blogname', 'Shop24');
        $custom = static fn (): string => 'Page 2 | Shop24';
        add_filter('pre_get_document_title', $custom, 15);

        $this->assertSame('Page ۲ | Shop24', wp_get_document_title());
    }

    public function test_category_lists_convert_names_and_counts_but_not_links(): void
    {
        $category = self::factory()->category->create(['name' => 'Top 10']);
        self::factory()->post->create(['post_status' => 'publish', 'post_category' => [$category]]);

        $html = wp_list_categories(['echo' => 0, 'show_count' => 1, 'hide_empty' => 1, 'title_li' => '']);

        $this->assertStringContainsString('>Top ۱۰</a>', $html);
        $this->assertStringContainsString('(۱)', $html);
        $this->assertStringContainsString('cat=' . $category, $html);
    }

    public function test_the_tag_cloud_converts_names_and_counts(): void
    {
        $tag = self::factory()->tag->create(['name' => 'Top 10']);
        foreach (self::factory()->post->create_many(2, ['post_status' => 'publish']) as $post) {
            wp_set_post_tags($post, [$tag]);
        }

        $html = (string) wp_tag_cloud(['echo' => false, 'show_count' => 1]);

        $this->assertStringContainsString('Top ۱۰<span class="tag-link-count"> (۲)</span>', $html);
        $this->assertStringContainsString('tag=top-10', $html);
        // Left as core builds it: the name as stored, the count from number_format_i18n.
        $this->assertStringContainsString('aria-label="Top 10 (۲ items)"', $html);
    }

    public function test_gregorian_archive_counts_convert_and_the_label_stays(): void
    {
        $this->bootDateConversionWith(['jalali_archives' => false]);
        self::factory()->post->create(['post_status' => 'publish', 'post_date' => '2025-03-21 10:00:00']);

        $html = wp_get_archives(['show_post_count' => 1, 'echo' => 0]);

        $this->assertStringContainsString('>March 2025</a>&nbsp;(۱)', $html);
        $this->assertStringContainsString('m=202503', $html);
    }

    public function test_jalali_archive_labels_and_counts_convert(): void
    {
        self::factory()->post->create(['post_status' => 'publish', 'post_date' => '2025-03-21 10:00:00']);

        $html = wp_get_archives(['show_post_count' => 1, 'echo' => 0]);

        $this->assertStringContainsString('>فروردین ۱۴۰۴</a>&nbsp;(۱)', $html);
        $this->assertStringContainsString('m=140401', $html);
    }

    public function test_block_widgets_convert_but_keep_their_code(): void
    {
        ob_start();
        the_widget('WP_Widget_Block', ['content' => '<!-- wp:paragraph --><p>Top 10 <code>v2</code></p><!-- /wp:paragraph -->']);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Top ۱۰ <code>v2</code>', $html);
    }

    public function test_navigation_labels_convert_and_their_links_do_not(): void
    {
        $html = do_blocks('<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:navigation-link {"label":"Top 10","url":"https://example.test/top-10/","kind":"custom"} /--><!-- /wp:navigation -->');

        $this->assertStringContainsString('Top ۱۰', $html);
        $this->assertStringContainsString('href="https://example.test/top-10/"', $html);
    }

    public function test_comment_excerpts_convert(): void
    {
        $comment = self::factory()->comment->create(['comment_post_ID' => self::factory()->post->create(), 'comment_content' => 'I have 10 cats']);

        $this->assertSame('I have ۱۰ cats', get_comment_excerpt($comment));
    }

    public function test_feeds_keep_their_digits(): void
    {
        $post = self::factory()->post->create(['post_title' => 'Top 10', 'post_status' => 'publish']);
        $this->go_to('/?feed=rss2');

        $this->assertTrue(is_feed());
        $this->assertSame('Top 10', get_the_title($post));
    }

    public function test_wp_head_tags_keep_their_digits(): void
    {
        $post = self::factory()->post->create(['post_title' => 'Top 10']);
        $title = null;
        $read = static function () use ($post, &$title): void {
            $title = get_the_title($post);
        };
        add_action('wp_head', $read);

        ob_start();
        do_action('wp_head');
        ob_end_clean();

        $this->assertSame('Top 10', $title);
    }

    private function bootDigits(): void
    {
        update_option(SettingsManager::OPTION_KEY, array_replace((array) get_option(SettingsManager::OPTION_KEY), [
            DigitConversionModule::key() => array_replace(DigitConversionModule::defaults(), ['enabled' => true]),
        ]));
        $manager = new SettingsManager();
        $manager->registerDefaults(DigitConversionModule::key(), DigitConversionModule::defaults());

        (new DigitConversionModule($manager))->boot(ServiceContainer::getInstance());
    }
}
