<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\CalendarNames;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class CalendarNamesTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        CalendarNames::reset();
    }

    protected function tearDown(): void
    {
        CalendarNames::reset();
        ContentLanguage::reset();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_each_set_is_complete(): void
    {
        foreach (CalendarNames::SETS as $set) {
            $names = CalendarNames::for($set);

            $this->assertSame(range(1, 12), array_keys($names['months']), $set);
            $this->assertSame(range(1, 12), array_keys($names['months_short']), $set);
            $this->assertSame(range(1, 12), array_keys($names['gregorian_months']), $set);
            $this->assertSame(range(0, 6), array_keys($names['weekdays']), $set);
            $this->assertSame(range(0, 6), array_keys($names['weekdays_short']), $set);
            $this->assertCount(4, $names['seasons'], $set);
            $this->assertCount(12, array_unique($names['months']), $set);
            $this->assertCount(7, array_unique($names['weekdays']), $set);

            foreach (['am', 'pm', 'am_long', 'pm_long'] as $key) {
                $this->assertNotSame('', $names[$key], "{$set} {$key}");
            }
        }
    }

    public function test_no_set_has_arabic_yeh_or_kaf_or_ezafe(): void
    {
        foreach (CalendarNames::SETS as $set) {
            $text = implode(' ', array_map(
                static fn ($value): string => is_array($value) ? implode(' ', $value) : $value,
                CalendarNames::for($set)
            ));

            $this->assertStringNotContainsString("\u{064A}", $text, "{$set}: Arabic yeh");
            $this->assertStringNotContainsString("\u{0643}", $text, "{$set}: Arabic kaf");
            $this->assertStringNotContainsString("\u{0654}", $text, "{$set}: ezafe");
        }
    }

    public function test_the_names_people_read(): void
    {
        $this->assertSame('میزان', CalendarNames::for('dari')['months'][7]);
        $this->assertSame('سنبله', CalendarNames::for('dari')['months'][6]);
        $this->assertSame('خزان', CalendarNames::for('dari')['seasons'][2]);
        $this->assertSame('تله', CalendarNames::for('pashto')['months'][7]);
        $this->assertSame('سې شنبه', CalendarNames::for('pashto')['weekdays'][2]);
        $this->assertSame('ڕەزبەر', CalendarNames::for('kurdish')['months'][7]);
        $this->assertSame('شەممە', CalendarNames::for('kurdish')['weekdays'][6]);
        $this->assertSame('مهر', CalendarNames::for('unknown')['months'][7], 'Iranian for an unknown set');
    }

    public function test_the_set_each_locale_reads(): void
    {
        $expected = [
            'fa_IR'  => 'iranian',
            'fa'     => 'iranian',
            'fa_AF'  => 'dari',
            'ps'     => 'pashto',
            'ps_AF'  => 'pashto',
            'ckb'    => 'kurdish',
            'ckb_IR' => 'kurdish',
            'en_US'  => 'iranian',
            'psx'    => 'iranian',
        ];

        foreach ($expected as $locale => $set) {
            $this->assertSame($set, CalendarNames::setFor($locale), $locale);
        }
    }

    public function test_before_the_settings_arrive_dates_use_the_iranian_names(): void
    {
        // No WordPress function is called: determine_locale() is not defined.
        $this->assertSame('iranian', CalendarNames::currentSet());
        $this->assertSame('مهر 1405', CalendarNames::monthAndYear(1405, 7));
    }

    public function test_a_single_language_site_follows_its_locale_or_the_choice(): void
    {
        ContentLanguage::useSource(null);
        Functions\when('determine_locale')->justReturn('fa_AF');

        CalendarNames::configure('auto');
        $this->assertSame('dari', CalendarNames::currentSet());
        $this->assertSame('میزان 1405', CalendarNames::monthAndYear(1405, 7));

        CalendarNames::configure('kurdish', ['fa_AF' => 'pashto']);
        $this->assertSame('kurdish', CalendarNames::currentSet(), 'the choices per language are for multilingual sites');
        $this->assertFalse(CalendarNames::isFixedFor('fa_AF'), 'a choice left from a multilingual plugin');

        CalendarNames::configure('roman');
        $this->assertSame('dari', CalendarNames::currentSet(), 'an unknown choice is automatic');
    }

    public function test_a_multilingual_site_follows_each_languages_choice(): void
    {
        CalendarNames::configure('kurdish', ['ps_AF' => 'dari', 'fa_IR' => 'auto']);

        $this->inLanguage('ps_AF');
        $this->assertSame('dari', CalendarNames::currentSet());

        $this->inLanguage('fa_IR');
        $this->assertSame('iranian', CalendarNames::currentSet(), 'the site choice is not used');

        $this->inLanguage('ckb');
        $this->assertSame('kurdish', CalendarNames::currentSet());

        $this->assertTrue(CalendarNames::isFixedFor('ps_AF'));
        $this->assertFalse(CalendarNames::isFixedFor('fa_IR'), 'automatic is not fixed');
    }

    public function test_the_filter_has_the_last_word(): void
    {
        ContentLanguage::useSource(null);
        Functions\when('determine_locale')->justReturn('fa_IR');
        CalendarNames::configure('auto');

        Filters\expectApplied('persian_kit_calendar_names')->once()->with('iranian', 'fa_IR')->andReturn('pashto');
        $this->assertSame('pashto', CalendarNames::currentSet());
        $this->assertSame('pashto', CalendarNames::currentSet(), 'kept for the locale');
    }

    public function test_an_unknown_set_from_the_filter_is_iranian(): void
    {
        ContentLanguage::useSource(null);
        Functions\when('determine_locale')->justReturn('fa_AF');
        CalendarNames::configure('auto');

        Filters\expectApplied('persian_kit_calendar_names')->andReturn('klingon');
        $this->assertSame('iranian', CalendarNames::currentSet());
    }
}
