<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\DateConversion\CalendarNames;
use PersianKit\Modules\DateConversion\PostTypeMonthFilter;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Dari, Pashto and Kurdish month names on a site in one language.
 */
class MonthNamesTest extends WordPressIntegrationTestCase
{
    private string $siteLocale = 'fa_IR';

    protected function setUp(): void
    {
        parent::setUp();

        update_option('timezone_string', 'Asia/Tehran');
        update_option('date_format', 'j F Y');
        add_filter('locale', fn (): string => $this->siteLocale);
        ContentLanguage::reset();
        CalendarNames::configure('auto');
    }

    protected function tearDown(): void
    {
        // As the plugin booted: automatic.
        CalendarNames::configure('auto');
        ContentLanguage::reset();

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sites(): array
    {
        return [
            'Dari'    => ['fa_AF', 'یکشنبه 12 میزان 1405', 'میزان 1405'],
            'Pashto'  => ['ps_AF', 'یکشنبه 12 تله 1405', 'تله 1405'],
            'Kurdish' => ['ckb', 'یەکشەممە 12 ڕەزبەر 1405', 'ڕەزبەر 1405'],
            'Iranian' => ['fa_IR', 'یکشنبه 12 مهر 1405', 'مهر 1405'],
        ];
    }

    /**
     * @dataProvider sites
     */
    public function test_the_site_language_gives_the_month_names(string $locale, string $postDate, string $month): void
    {
        $this->siteLocale = $locale;
        $postId = $this->postOnOctober4();

        $this->assertSame($postDate, get_the_date('l j F Y', $postId));

        $this->go_to('/?m=140507');
        $this->assertTrue(is_month());
        $this->assertStringContainsString($month, get_the_archive_title());

        $filter = new PostTypeMonthFilter();
        $this->assertContains(
            ['value' => '140507', 'label' => str_replace('1405', '۱۴۰۵', $month)],
            $filter->monthOptions('post')
        );
    }

    public function test_the_setting_overrides_the_language(): void
    {
        $postId = $this->postOnOctober4();

        CalendarNames::configure('dari');
        $this->assertSame('12 میزان 1405', get_the_date('', $postId));

        $this->siteLocale = 'fa_AF';
        CalendarNames::configure('iranian');
        $this->assertSame('12 مهر 1405', get_the_date('', $postId));
    }

    public function test_the_month_names_setting_is_saved(): void
    {
        update_option('persian_kit_settings', [
            'date_conversion' => [
                'enabled'               => '1',
                'month_names'           => 'kurdish',
                'month_names_by_locale' => ['ps_AF' => 'dari', 'en_US' => 'nope'],
            ],
        ]);

        $saved = get_option('persian_kit_settings')['date_conversion'];
        $this->assertSame('kurdish', $saved['month_names']);
        $this->assertSame(['ps_AF' => 'dari'], $saved['month_names_by_locale']);
    }

    private function postOnOctober4(): int
    {
        return self::factory()->post->create([
            'post_status'   => 'publish',
            'post_date'     => '2026-10-04 10:00:00',
            'post_date_gmt' => '2026-10-04 06:30:00',
        ]);
    }
}
