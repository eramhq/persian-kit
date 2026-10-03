<?php

namespace PersianKit\Tests\Unit\Import;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Import\Sources\ParsiDate\BlockRewriter;
use PHPUnit\Framework\TestCase;

class BlockRewriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
        Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
        Functions\when('wp_json_encode')->alias(static fn ($value, int $flags = 0) => json_encode($value, $flags));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_the_archive_block_with_its_settings(): void
    {
        $content = "<!-- wp:paragraph -->\n<p>Before</p>\n<!-- /wp:paragraph -->\n\n"
            . '<!-- wp:wp-parsidate/archive {"title":"بایگانی سال‌ها","type":"yearly","displaySelect":true,"displayCount":true,"className":"is-style-x"} /-->'
            . "\n\n<!-- wp:paragraph -->\n<p>After</p>\n<!-- /wp:paragraph -->";

        $rewritten = (new BlockRewriter())->rewrite($content);

        $this->assertSame(
            "<!-- wp:paragraph -->\n<p>Before</p>\n<!-- /wp:paragraph -->\n\n"
            . "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">بایگانی سال‌ها</h2>\n<!-- /wp:heading -->\n\n"
            . '<!-- wp:archives {"type":"yearly","displayAsDropdown":true,"showPostCounts":true,"className":"is-style-x"} /-->'
            . "\n\n<!-- wp:paragraph -->\n<p>After</p>\n<!-- /wp:paragraph -->",
            $rewritten
        );
    }

    public function test_defaults_give_the_titles_parsi_date_showed(): void
    {
        $rewriter = new BlockRewriter();

        $this->assertSame(
            "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">بایگانی</h2>\n<!-- /wp:heading -->\n\n<!-- wp:archives /-->",
            $rewriter->rewrite('<!-- wp:wp-parsidate/archive /-->', 'بایگانی')
        );
        $this->assertSame(
            "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Calendar</h2>\n<!-- /wp:heading -->\n\n<!-- wp:calendar /-->",
            $rewriter->rewrite('<!-- wp:wp-parsidate/calendar {"theme":"dark"} /-->')
        );
        // An empty title gives no heading.
        $this->assertSame('<!-- wp:calendar /-->', $rewriter->rewrite('<!-- wp:wp-parsidate/calendar {"title":""} /-->'));
    }

    public function test_a_block_with_inner_markup(): void
    {
        $this->assertSame(
            '<!-- wp:archives {"type":"daily"} /-->',
            (new BlockRewriter())->rewrite('<!-- wp:wp-parsidate/archive {"title":"","type":"daily"} --><div>old</div><!-- /wp:wp-parsidate/archive -->')
        );
    }

    public function test_another_post_type_is_left_alone(): void
    {
        $rewriter = new BlockRewriter();
        $block = '<!-- wp:wp-parsidate/archive {"postType":"product"} /-->';

        $this->assertSame($block, $rewriter->rewrite($block));
        $this->assertCount(1, $rewriter->skipped());
        $this->assertTrue(BlockRewriter::hasBlocks($block));
        $this->assertFalse(BlockRewriter::hasBlocks('<!-- wp:archives /-->'));
    }
}
