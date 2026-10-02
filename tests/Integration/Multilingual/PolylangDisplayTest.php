<?php

namespace PersianKit\Tests\Integration\Multilingual;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Integration\Support\UsesPolylang;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Pages show Jalali dates and Persian digits only in Persian.
 *
 * @group polylang
 */
class PolylangDisplayTest extends WordPressIntegrationTestCase
{
    use UsesPolylang;

    public function set_up(): void
    {
        parent::set_up();
        $this->setUpPolylang();

        update_option('timezone_string', 'Asia/Tehran');
        update_option('date_format', 'j F Y');
    }

    public function test_post_dates_follow_the_pages_language(): void
    {
        $post = get_post($this->postIn('en', ['post_date' => '2026-10-02 10:00:00']));

        $this->assertSame('1405/07/10', get_the_date('Y/m/d', $post));
        $this->assertSame('10 مهر 1405', get_the_date('', $post));

        $this->useLanguage('en');
        $this->assertSame('2026/10/02', get_the_date('Y/m/d', $post));
        $this->assertSame('2 October 2026', get_the_date('', $post));
    }

    public function test_digits_follow_the_pages_language(): void
    {
        $this->bootDigits();
        $postId = $this->postIn('en', ['post_title' => 'Top 10']);

        $this->assertSame('Top ۱۰', get_the_title($postId));

        $this->useLanguage('en');
        $this->assertSame('Top 10', get_the_title($postId));
    }

    public function test_the_archive_list_follows_the_pages_language(): void
    {
        $this->postIn('fa', ['post_date' => '2026-10-02 10:00:00']);
        $this->postIn('en', ['post_date' => '2026-10-02 10:00:00']);

        $persian = wp_get_archives(['type' => 'monthly', 'echo' => false]);
        $this->assertStringContainsString('مهر 1405', $persian);
        $this->assertStringNotContainsString('October', $persian);

        $this->useLanguage('en');
        $english = wp_get_archives(['type' => 'monthly', 'echo' => false]);
        $this->assertStringContainsString('October 2026', $english);
        $this->assertStringNotContainsString('مهر', $english);
    }

    public function test_the_calendar_follows_the_pages_language(): void
    {
        $this->postIn('fa', ['post_date' => '2026-10-02 10:00:00']);
        $this->postIn('en', ['post_date' => '2026-10-02 10:00:00']);
        $GLOBALS['monthnum'] = '10';
        $GLOBALS['year'] = '2026';

        $this->assertStringContainsString('<caption>مهر 1405</caption>', get_calendar(['display' => false]));

        $this->useLanguage('en');
        $this->assertStringContainsString('<caption>October 2026</caption>', get_calendar(['display' => false]));

        unset($GLOBALS['monthnum'], $GLOBALS['year']);
    }

    public function test_date_archive_titles_follow_the_pages_language(): void
    {
        $this->postIn('fa', ['post_date' => '2026-10-02 10:00:00']);
        $this->postIn('en', ['post_date' => '2026-10-02 10:00:00']);

        $this->go_to('/?m=202610');
        $this->useLanguage('fa');
        $this->assertTrue(is_month());
        $this->assertStringContainsString('مهر – آبان 1405', get_the_archive_title());

        $this->go_to('/?m=202610&lang=en');
        $this->useLanguage('en');
        $this->assertTrue(is_month());
        $this->assertStringContainsString('October 2026', get_the_archive_title());
    }

    public function test_a_switched_locale_wins_over_the_page(): void
    {
        $post = get_post($this->postIn('fa', ['post_date' => '2026-10-02 10:00:00']));

        switch_to_locale('en_US');
        $date = get_the_date('Y/m/d', $post);
        restore_previous_locale();

        $this->assertSame('2026/10/02', $date);
    }

    private function bootDigits(): void
    {
        update_option(SettingsManager::OPTION_KEY, ['digit_conversion' => array_replace(DigitConversionModule::defaults(), ['enabled' => true])]);
        $manager = new SettingsManager();
        $manager->registerDefaults(DigitConversionModule::key(), DigitConversionModule::defaults());

        (new DigitConversionModule($manager))->boot(ServiceContainer::getInstance());
    }
}
