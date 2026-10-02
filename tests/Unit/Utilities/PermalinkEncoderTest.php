<?php

namespace PersianKit\Tests\Unit\Utilities;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Utilities\PermalinkEncoder;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PHPUnit\Framework\TestCase;

class PermalinkEncoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_persian_letters_in_the_path_are_percent_encoded(): void
    {
        $this->assertSame(
            'https://example.test/' . rawurlencode('برنامه-سال') . '-1405/',
            PermalinkEncoder::encode('https://example.test/برنامه-سال-1405/')
        );
    }

    public function test_the_encoded_link_decodes_back_to_the_slug(): void
    {
        $link = 'https://example.test/category/کتاب‌های-کودک/?ref=صفحه#بخش';

        $this->assertSame($link, rawurldecode(PermalinkEncoder::encode($link)));
        $this->assertDoesNotMatchRegularExpression('/[\x80-\xFF]/', PermalinkEncoder::encode($link));
    }

    public function test_the_encoded_link_survives_parse_url(): void
    {
        // parse_url() is what broke the raw link in some locales.
        $path = parse_url(PermalinkEncoder::encode('https://example.test/مهر/'), PHP_URL_PATH);

        $this->assertSame('/مهر/', rawurldecode((string) $path));
    }

    /**
     * @dataProvider unchangedLinks
     */
    public function test_links_without_raw_unicode_are_left_as_they_are(mixed $link): void
    {
        $this->assertSame($link, PermalinkEncoder::encode($link));
    }

    public static function unchangedLinks(): array
    {
        return [
            'latin'           => ['https://example.test/hello-world/'],
            'already encoded' => ['https://example.test/%d8%b3%d9%84%d8%a7%d9%85/'],
            'plain permalink' => ['https://example.test/?p=12'],
            'not a string'    => [false],
        ];
    }

    public function test_an_internationalized_host_is_left_alone(): void
    {
        $this->assertSame(
            'https://مثال.ir/' . rawurlencode('سلام') . '/',
            PermalinkEncoder::encode('https://مثال.ir/سلام/')
        );
    }

    public function test_a_relative_link_is_encoded_from_the_start(): void
    {
        $this->assertSame('/' . rawurlencode('سلام') . '/', PermalinkEncoder::encode('/سلام/'));
    }

    public function test_register_encodes_post_page_attachment_and_term_links_late(): void
    {
        (new PermalinkEncoder())->register();

        foreach (['post_link', 'page_link', 'post_type_link', 'attachment_link', 'term_link'] as $filter) {
            $this->assertSame(99, has_filter($filter, [PermalinkEncoder::class, 'encode']), $filter);
        }
    }

    public function test_module_encodes_links_even_with_persian_slugs_off(): void
    {
        $this->bootUtilities(['enabled' => true, 'persian_slugs' => false]);

        $this->assertNotFalse(has_filter('post_link', [PermalinkEncoder::class, 'encode']));
        $this->assertFalse(has_filter('sanitize_title'));
    }

    public function test_the_utilities_filter_can_turn_link_encoding_off(): void
    {
        Filters\expectApplied('persian_kit_utilities')->andReturnUsing(
            static fn (bool $enabled, string $feature): bool => $feature !== 'encode_links'
        );

        $this->bootUtilities(['enabled' => true]);

        $this->assertFalse(has_filter('post_link', [PermalinkEncoder::class, 'encode']));
        $this->assertNotFalse(has_filter('sanitize_title'));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function bootUtilities(array $settings): void
    {
        $merged = array_replace(UtilitiesModule::defaults(), $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            static fn (string $module, ?string $key = null, mixed $default = null) => $key === null ? $merged : ($merged[$key] ?? $default)
        );

        $container = ServiceContainer::getInstance();
        $module = new UtilitiesModule($manager);
        $module->register($container);
        $module->boot($container);
    }
}
