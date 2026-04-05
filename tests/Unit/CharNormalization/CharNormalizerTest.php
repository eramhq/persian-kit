<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\CharNormalization\CharNormalizer;

class CharNormalizerTest extends TestCase
{
    private CharNormalizer $normalizer;
    private CharNormalizer $normalizerWithTehMarbuta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CharNormalizer();
        $this->normalizerWithTehMarbuta = new CharNormalizer(tehMarbuta: true);
    }

    // ── normalize() ───────────────────────────────────────────────────

    public function test_normalize_converts_arabic_yeh_to_persian(): void
    {
        // Arabic Yeh: ي (U+064A) → Persian Yeh: ی (U+06CC)
        $this->assertSame("سلامی", $this->normalizer->normalize("سلامي"));
    }

    public function test_normalize_converts_arabic_kaf_to_persian(): void
    {
        // Arabic Kaf: ك (U+0643) → Persian Kaf: ک (U+06A9)
        $this->assertSame("کتاب", $this->normalizer->normalize("كتاب"));
    }

    public function test_normalize_converts_arabic_digits_to_persian(): void
    {
        // Arabic digits U+0660–U+0669 → Persian digits U+06F0–U+06F9
        $this->assertSame("۰۱۲", $this->normalizer->normalize("٠١٢"));
    }

    public function test_normalize_converts_mixed_arabic_chars(): void
    {
        $input    = "كتابي"; // Arabic Kaf + Arabic Yeh
        $expected = "کتابی"; // Persian Kaf + Persian Yeh
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function test_normalize_does_not_convert_teh_marbuta_by_default(): void
    {
        $input = "جامعة"; // Arabic Teh Marbuta
        $this->assertSame($input, $this->normalizer->normalize($input));
    }

    public function test_normalize_converts_teh_marbuta_when_enabled(): void
    {
        $input    = "جامعة"; // Teh Marbuta
        $expected = "جامعه"; // Persian Heh
        $this->assertSame($expected, $this->normalizerWithTehMarbuta->normalize($input));
    }

    public function test_normalize_is_idempotent(): void
    {
        $input = "كتابي ٠١٢";
        $first  = $this->normalizer->normalize($input);
        $second = $this->normalizer->normalize($first);
        $this->assertSame($first, $second);
    }

    public function test_normalize_leaves_persian_text_unchanged(): void
    {
        $persian = "سلام کتابی ۰۱۲۳";
        $this->assertSame($persian, $this->normalizer->normalize($persian));
    }

    // ── normalizeContent() ────────────────────────────────────────────

    public function test_normalize_content_empty_string(): void
    {
        $this->assertSame('', $this->normalizer->normalizeContent(''));
    }

    public function test_normalize_content_plain_text(): void
    {
        $this->assertSame("کتابی", $this->normalizer->normalizeContent("كتابي"));
    }

    public function test_normalize_content_skips_html_attributes(): void
    {
        $html     = '<a href="كتاب">كتاب</a>';
        $expected = '<a href="كتاب">کتاب</a>';
        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    public function test_normalize_content_skips_script_blocks(): void
    {
        $html     = '<script>var x = "كتاب";</script><p>كتاب</p>';
        $expected = '<script>var x = "كتاب";</script><p>کتاب</p>';
        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    public function test_normalize_content_skips_style_blocks(): void
    {
        $html     = '<style>.كتاب { color: red; }</style><span>كتاب</span>';
        $expected = '<style>.كتاب { color: red; }</style><span>کتاب</span>';
        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    public function test_normalize_content_skips_html_comments(): void
    {
        $html     = '<!-- wp:paragraph {"كتاب":true} --><p>كتاب</p><!-- /wp:paragraph -->';
        $expected = '<!-- wp:paragraph {"كتاب":true} --><p>کتاب</p><!-- /wp:paragraph -->';
        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    public function test_normalize_content_handles_gutenberg_blocks(): void
    {
        $html = '<!-- wp:image {"id":123,"alt":"كتاب"} -->'
              . '<figure class="wp-block-image"><img src="img.jpg" alt="كتاب"/>'
              . '<figcaption>كتاب زيبا</figcaption>'
              . '</figure>'
              . '<!-- /wp:image -->';

        $expected = '<!-- wp:image {"id":123,"alt":"كتاب"} -->'
                  . '<figure class="wp-block-image"><img src="img.jpg" alt="كتاب"/>'
                  . '<figcaption>کتاب زیبا</figcaption>'
                  . '</figure>'
                  . '<!-- /wp:image -->';

        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    public function test_normalize_content_normalizes_text_between_tags(): void
    {
        $html     = '<div><p>كتابي</p><span>سلامي</span></div>';
        $expected = '<div><p>کتابی</p><span>سلامی</span></div>';
        $this->assertSame($expected, $this->normalizer->normalizeContent($html));
    }

    // ── normalizeForSearch() ──────────────────────────────────────────

    public function test_normalize_for_search_normalizes_arabic_chars(): void
    {
        $this->assertSame("کتاب", $this->normalizer->normalizeForSearch("كتاب"));
    }

    public function test_normalize_for_search_converts_persian_digits_to_english(): void
    {
        $this->assertSame("123", $this->normalizer->normalizeForSearch("۱۲۳"));
    }

    public function test_normalize_for_search_converts_arabic_digits_to_english(): void
    {
        $this->assertSame("456", $this->normalizer->normalizeForSearch("٤٥٦"));
    }
}
