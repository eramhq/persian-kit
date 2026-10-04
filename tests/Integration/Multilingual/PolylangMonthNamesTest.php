<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Modules\DateConversion\CalendarNames;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Each language's month names, and Jalali dates on Pashto pages.
 *
 * @group polylang
 */
class PolylangMonthNamesTest extends WordPressIntegrationTestCase
{
    use UsesPolylang;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();

        $blockHttp = static fn () => new \WP_Error('http_blocked', 'No HTTP requests in tests.');
        add_filter('pre_http_request', $blockHttp);
        $pashto = PLL()->model->languages->add(['locale' => 'ps', 'slug' => 'ps', 'name' => 'پښتو', 'rtl' => 1, 'term_group' => 3, 'no_default_cat' => true]);
        remove_filter('pre_http_request', $blockHttp);
        if (is_wp_error($pashto)) {
            $this->fail('Polylang could not add ps: ' . $pashto->get_error_message());
        }

        update_option('timezone_string', 'Asia/Tehran');
        update_option('date_format', 'j F Y');
        CalendarNames::configure('auto');
    }

    public function tear_down(): void
    {
        CalendarNames::configure('auto');
        parent::tear_down();
    }

    public function test_each_page_shows_its_own_month_names(): void
    {
        $post = get_post($this->postIn('fa', ['post_date' => '2026-10-04 10:00:00']));

        $this->assertSame('12 مهر 1405', get_the_date('', $post));

        $this->useLanguage('ps');
        $this->assertTrue(ContentLanguage::displaysPersian(), 'Pashto pages show Jalali dates');
        $this->assertFalse(ContentLanguage::writesPersian('post'), 'but are not written in Persian');
        $this->assertSame('12 تله 1405', get_the_date('', $post));

        $this->useLanguage('en');
        $this->assertSame('4 October 2026', get_the_date('', $post));
    }

    public function test_a_languages_choice_overrides_automatic(): void
    {
        CalendarNames::configure('kurdish', ['fa_IR' => 'dari', 'en_US' => 'iranian']);
        $post = get_post($this->postIn('fa', ['post_date' => '2026-10-04 10:00:00']));

        $this->assertSame('12 میزان 1405', get_the_date('', $post));

        $this->useLanguage('ps');
        $this->assertSame('12 تله 1405', get_the_date('', $post), 'the site choice is not used');

        $this->useLanguage('en');
        $this->assertSame('12 مهر 1405', get_the_date('', $post), 'a language given names shows Jalali dates');
    }
}
