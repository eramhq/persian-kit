<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\DatePicker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class DatePickerTest extends TestCase
{
    /** @var array<string, list<mixed>> */
    private array $scripts = [];

    /** @var array<string, list<mixed>> */
    private array $styles = [];

    /** @var list<array{string, string, string}> */
    private array $inline = [];

    /** @var list<string> */
    private array $enqueued = [];

    private string $locale = 'fa_IR';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }

        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        Functions\when('wp_script_is')->alias(fn (string $handle) => isset($this->scripts[$handle]));
        Functions\when('wp_register_script')->alias(function (string $handle, ...$args) {
            $this->scripts[$handle] = $args;
        });
        Functions\when('wp_register_style')->alias(function (string $handle, ...$args) {
            $this->styles[$handle] = $args;
        });
        Functions\when('wp_add_inline_script')->alias(function (string $handle, string $code, string $position) {
            $this->inline[] = [$handle, $code, $position];
        });
        Functions\when('wp_enqueue_script')->alias(function (string $handle) {
            $this->enqueued[] = "script:{$handle}";
        });
        Functions\when('wp_enqueue_style')->alias(function (string $handle) {
            $this->enqueued[] = "style:{$handle}";
        });
        Functions\when('determine_locale')->alias(fn () => $this->locale);
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_the_picker_and_the_field_script(): void
    {
        DatePicker::register();

        $this->assertSame([
            'persian-kit-datepicker' => [PERSIAN_KIT_URL . 'public/js/datepicker.js', [], PERSIAN_KIT_VERSION, true],
            'persian-kit-date-field' => [PERSIAN_KIT_URL . 'public/js/date-field.js', ['persian-kit-datepicker'], PERSIAN_KIT_VERSION, true],
        ], $this->scripts);
        $this->assertSame([
            'persian-kit-date-field' => [PERSIAN_KIT_URL . 'public/css/date-field.css', [], PERSIAN_KIT_VERSION],
        ], $this->styles);

        $this->assertCount(1, $this->inline);
        [$handle, $code, $position] = $this->inline[0];
        $this->assertSame('persian-kit-date-field', $handle);
        $this->assertSame('before', $position);
        $this->assertSame('window.persianKitDateField = {"locale":"fa-IR","labels":{"time":"Time"}};', $code);
    }

    public function test_register_runs_once(): void
    {
        DatePicker::register();
        DatePicker::register();

        $this->assertCount(1, $this->inline);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        return [
            'Persian'          => ['fa_IR', 'fa-IR'],
            'Persian, no area' => ['fa', 'fa'],
            'English'          => ['en_US', 'en-US'],
            'variant'          => ['pt_PT_ao90', 'pt-PT'],
            'formal'           => ['de_DE_formal', 'de-DE'],
            'unknown'          => ['', 'fa-IR'],
        ];
    }

    #[DataProvider('locales')]
    public function test_the_picker_uses_the_page_language(string $locale, string $expected): void
    {
        $this->locale = $locale;

        DatePicker::register();

        $this->assertStringContainsString('"locale":"' . $expected . '"', $this->inline[0][1]);
    }

    public function test_enqueue_loads_the_field_script_and_style(): void
    {
        DatePicker::enqueue();

        $this->assertSame(['script:persian-kit-date-field', 'style:persian-kit-date-field'], $this->enqueued);
        $this->assertArrayHasKey('persian-kit-datepicker', $this->scripts);
    }

    public function test_attributes_default_to_a_plain_date_field(): void
    {
        $this->assertSame(['data-persian-kit-date' => ''], DatePicker::attributes());
    }

    public function test_attributes_pass_the_options(): void
    {
        $this->assertSame([
            'data-persian-kit-date'                => '',
            'data-persian-kit-date-format'         => 'Ymd',
            'data-persian-kit-date-type'           => 'range',
            'data-persian-kit-date-min'            => '2026-03-21',
            'data-persian-kit-date-max'            => '2026-12-31',
            'data-persian-kit-date-disable-past'   => '',
            'data-persian-kit-date-disable-future' => '',
            'data-persian-kit-date-locale'         => 'en-US',
        ], DatePicker::attributes([
            'format'         => 'Ymd',
            'type'           => 'range',
            'min'            => '۱۴۰۵/۰۱/۰۱',
            'max'            => '2026-12-31',
            'disable_past'   => true,
            'disable_future' => true,
            'locale'         => 'en-US',
        ]));
    }

    public function test_day_month_year_formats_in_any_order(): void
    {
        foreach (['d/m/Y', 'm.d.Y', 'Y/m/d', 'd-m-Y'] as $format) {
            $this->assertTrue(DatePicker::isFormat($format), $format);
            $this->assertSame($format, DatePicker::attributes(['format' => $format])['data-persian-kit-date-format'] ?? null, $format);
        }

        foreach (['d/m/y', 'd/d/Y', 'd/m-Y', 'dmY', 'D, d M Y', 'Y-m-d H:i'] as $format) {
            $this->assertFalse(DatePicker::isFormat($format), $format);
        }
    }

    public function test_attributes_skip_unknown_options(): void
    {
        $this->assertSame(['data-persian-kit-date' => ''], DatePicker::attributes([
            'format'       => 'd/m/y',
            'type'         => 'week',
            'min'          => 'yesterday',
            'disable_past' => false,
        ]));
    }

    public function test_attributes_html_is_escaped(): void
    {
        $this->assertSame(
            ' data-persian-kit-date data-persian-kit-date-locale="&quot;&gt;"',
            DatePicker::attributesHtml(DatePicker::attributes(['locale' => '">']))
        );
    }

    public function test_the_helper_loads_the_picker_and_returns_the_attributes(): void
    {
        $html = persian_kit_date_field_attributes(['max' => '2010-12-31']);

        $this->assertSame(' data-persian-kit-date data-persian-kit-date-max="2010-12-31"', $html);
        $this->assertContains('script:persian-kit-date-field', $this->enqueued);
    }
}
