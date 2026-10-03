<?php

namespace PersianKit\Tests\Integration\Import;

use PersianKit\Service\Import\Sources\ParsiDate\ThemeScanner;
use PersianKit\Service\Import\Sources\ParsiDate\ThemeSnippet;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Theme code calling Parsi Date's functions, and the code that keeps it
 * working with Persian Kit's.
 */
class ThemeScanTest extends WordPressIntegrationTestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures/theme-scan';

    public function test_finds_calls_and_tells_guarded_and_defined_ones_apart(): void
    {
        $scanner = new ThemeScanner([self::FIXTURES . '/theme', self::FIXTURES . '/child']);

        $calls = $scanner->calls(ThemeScanner::PARSI_DATE, true)['calls'];
        $found = array_map(static fn (array $call): string => basename($call['file']) . ':' . $call['line'] . ' ' . $call['function'] . ($call['guarded'] ? ' (guarded)' : ''), $calls);
        sort($found);

        $this->assertSame([
            'functions.php:3 parsidate',
            'functions.php:4 per_number',
            'functions.php:7 gregdate (guarded)',
            'single.php:3 per_number',
            // The child theme defines it now.
            'single.php:4 wp_get_parchives (guarded)',
        ], $found);
    }

    public function test_finds_blocks_in_template_files(): void
    {
        $scanner = new ThemeScanner([self::FIXTURES . '/theme']);

        $this->assertSame(['home.html'], array_map('basename', $scanner->blockTemplates()));
    }

    public function test_finds_elementor_pages_with_its_widgets(): void
    {
        $page = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Home']);
        update_post_meta($page, '_elementor_data', wp_slash('[{"widgetType":"wp-widget-wp_parsidate_archive","settings":{}}]'));
        $other = self::factory()->post->create(['post_type' => 'page']);
        update_post_meta($other, '_elementor_data', wp_slash('[{"widgetType":"heading"}]'));

        $this->assertSame([$page], ThemeScanner::elementorPages());
    }

    public function test_the_snippet_keeps_the_calls_working(): void
    {
        update_option('timezone_string', 'Asia/Tehran');
        $snippet = ThemeSnippet::build(['parsidate', 'gregdate', 'per_number', 'eng_number', 'fix_number', 'wp_get_parchives', 'wpp_is_active', 'disable_wpp', 'wpp_date_is', 'unknown']);

        $this->assertStringNotContainsString('unknown', $snippet);
        $this->assertSame('', ThemeSnippet::build(['nothing']));

        // Valid PHP, defining each function once.
        $file = wp_tempnam('snippet') . '.php';
        file_put_contents($file, "<?php\n" . $snippet);
        exec('php -l ' . escapeshellarg($file), $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));

        if (function_exists('parsidate')) {
            $this->markTestSkipped('Parsi Date is loaded.');
        }
        require $file;

        // 2024-08-02 00:30 in Tehran: still 12 Mordad there, 11 Mordad in UTC.
        $this->assertSame('۱۴۰۳/۰۵/۱۲', parsidate('Y/m/d', '2024-08-02 00:30:00'));
        $this->assertSame('1403/05/12', parsidate('Y/m/d', '2024-08-02 00:30:00', 'eng'));
        $this->assertSame('2024-08-02', gregdate('Y-m-d', '1403-05-12'));
        $this->assertFalse(gregdate('Y-m-d', 'soon'));
        $this->assertSame('۱۲۳', per_number('123'));
        $this->assertSame('123', eng_number('۱۲۳'));
        $this->assertFalse(wpp_is_active('persian_date'));
        $this->assertIsString(wp_get_parchives(['echo' => 0]));
    }
}
