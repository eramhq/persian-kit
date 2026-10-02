<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\Cf7InputNormalizer;
use PersianKit\Tests\Unit\Forms\Support\FakeFormTag;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class Cf7InputNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('wp_slash')->alias('addslashes');
    }

    protected function tearDown(): void
    {
        unset($_POST['visit'], $_POST['phone'], $_POST['count']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_covers_required_and_optional_fields(): void
    {
        $normalizer = new Cf7InputNormalizer();
        $normalizer->register();

        foreach (['date', 'date*', 'tel', 'tel*', 'number', 'number*', 'range', 'range*'] as $type) {
            $this->assertNotFalse(has_filter("wpcf7_posted_data_{$type}", [$normalizer, 'normalizePostedValue']), $type);
        }
    }

    public function test_a_typed_jalali_date_is_sent_and_checked_as_gregorian(): void
    {
        // CF7's checks read $_POST, which WordPress keeps slashed.
        $_POST['visit'] = '۱۴۰۵/۰۷/۱۰';

        $value = (new Cf7InputNormalizer())->normalizePostedValue('۱۴۰۵/۰۷/۱۰', '۱۴۰۵/۰۷/۱۰', new FakeFormTag('date*', 'visit'));

        $this->assertSame('2026-10-02', $value);
        $this->assertSame('2026-10-02', $_POST['visit']);
    }

    public function test_a_gregorian_date_from_the_picker_is_kept(): void
    {
        $_POST['visit'] = '2026-10-02';

        $this->assertSame('2026-10-02', (new Cf7InputNormalizer())->normalizePostedValue('2026-10-02', '2026-10-02', new FakeFormTag('date', 'visit')));
        $this->assertSame('2026-10-02', $_POST['visit']);
    }

    public function test_an_invalid_date_is_left_for_cf7_to_report(): void
    {
        $_POST['visit'] = '۱۴۰۴/۱۲/۳۰';

        $this->assertSame('1404/12/30', (new Cf7InputNormalizer())->normalizePostedValue('۱۴۰۴/۱۲/۳۰', '', new FakeFormTag('date', 'visit')));
        $this->assertSame('1404/12/30', $_POST['visit']);
    }

    public function test_phone_and_number_digits_become_english(): void
    {
        $_POST['phone'] = '+۹۸ (۲۱) ۸۸۷۷';
        $_POST['count'] = '٤٢';
        $normalizer = new Cf7InputNormalizer();

        $this->assertSame('+98 (21) 8877', $normalizer->normalizePostedValue('+۹۸ (۲۱) ۸۸۷۷', '', new FakeFormTag('tel', 'phone')));
        $this->assertSame('42', $normalizer->normalizePostedValue('٤٢', '', new FakeFormTag('number*', 'count')));
        $this->assertSame('+98 (21) 8877', $_POST['phone']);
        $this->assertSame('42', $_POST['count']);
    }

    public function test_post_keys_are_not_added(): void
    {
        $this->assertSame('42', (new Cf7InputNormalizer())->normalizePostedValue('۴۲', '', new FakeFormTag('number', 'count')));
        $this->assertArrayNotHasKey('count', $_POST);
    }

    public function test_other_values_are_left_alone(): void
    {
        $normalizer = new Cf7InputNormalizer();

        $this->assertSame(['۴۲'], $normalizer->normalizePostedValue(['۴۲'], [], new FakeFormTag('number', 'count')));
        $this->assertSame('', $normalizer->normalizePostedValue('', '', new FakeFormTag('date', 'visit')));
        $this->assertSame('۴۲', $normalizer->normalizePostedValue('۴۲', '', new FakeFormTag('text', 'name')));
    }
}
