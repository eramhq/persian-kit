<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\WPFormsInputNormalizer;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class WPFormsInputNormalizerTest extends TestCase
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

    public function test_it_fixes_the_submission_before_wpforms_checks_it_and_loads_with_the_forms_scripts(): void
    {
        $normalizer = new WPFormsInputNormalizer();
        $normalizer->register();

        $this->assertNotFalse(has_filter('wpforms_process_before_filter', [$normalizer, 'normalizeEntry']));
        $this->assertNotFalse(has_action('wpforms_frontend_js', [$normalizer, 'enqueue']));
    }

    public function test_numbers_prices_and_dates_get_english_digits_and_text_keeps_its_own(): void
    {
        $entry = (new WPFormsInputNormalizer())->normalizeEntry(
            ['id' => '5', 'fields' => [1 => '١٢', 2 => '۷', 3 => '۲۵,۰۰۰', 4 => '۱۴۰۵/۰۷/۱۲', 5 => 'پلاک ۱۲', 6 => ['۱', '۲'], 7 => '۰۹۱۲ ۱۲۳ ۴۵۶۷', 8 => 'abc ۱۲']],
            ['id' => 5, 'fields' => [
                1 => ['id' => 1, 'type' => 'number'],
                2 => ['id' => 2, 'type' => 'number-slider'],
                3 => ['id' => 3, 'type' => 'payment-single'],
                4 => ['id' => 4, 'type' => 'persian-kit-date'],
                5 => ['id' => 5, 'type' => 'text'],
                6 => ['id' => 6, 'type' => 'checkbox'],
                7 => ['id' => 7, 'type' => 'persian-kit-mobile'],
                8 => ['id' => 8, 'type' => 'persian-kit-postcode'],
                9 => ['id' => 9, 'type' => 'number'],
            ]]
        );

        $this->assertSame(
            ['id' => '5', 'fields' => [1 => '12', 2 => '7', 3 => '25,000', 4 => '1405/07/12', 5 => 'پلاک ۱۲', 6 => ['۱', '۲'], 7 => '09121234567', 8 => 'abc 12']],
            $entry
        );
    }

    public function test_other_submissions_are_left_alone(): void
    {
        $normalizer = new WPFormsInputNormalizer();

        $this->assertSame('x', $normalizer->normalizeEntry('x', []));
        $this->assertSame(['id' => 5], $normalizer->normalizeEntry(['id' => 5], ['fields' => [['id' => 1, 'type' => 'number']]]));
    }

    public function test_the_digit_script_is_loaded_with_forms_that_have_such_fields(): void
    {
        if (!defined('PERSIAN_KIT_URL')) {
            define('PERSIAN_KIT_URL', 'https://example.com/wp-content/plugins/persian-kit/');
        }
        if (!defined('PERSIAN_KIT_VERSION')) {
            define('PERSIAN_KIT_VERSION', '1.0.0');
        }

        $enqueued = [];
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_enqueue_script')->alias(function (string $handle, string $src, array $deps) use (&$enqueued) {
            $enqueued[] = [$handle, basename($src), $deps];
        });

        $normalizer = new WPFormsInputNormalizer();
        $normalizer->enqueue([5 => ['id' => 5, 'fields' => [['id' => 1, 'type' => 'text'], ['id' => 2, 'type' => 'number-slider'], ['id' => 3, 'type' => 'persian-kit-date']]]]);
        $this->assertSame([], $enqueued);

        $normalizer->enqueue([5 => ['id' => 5, 'fields' => [['id' => 1, 'type' => 'text']]], 6 => ['id' => 6, 'fields' => [['id' => 1, 'type' => 'persian-kit-iban']]]]);
        $this->assertSame([[WPFormsInputNormalizer::SCRIPT, 'wpforms-digits.js', ['persian-kit-form-digits']]], $enqueued);
    }
}
