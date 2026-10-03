<?php

namespace PersianKit\Tests\Integration\Sources;

use PersianKit\Modules\DateConversion\JalaliPeriod;
use PersianKit\Tests\Integration\Support\BootsDateConversion;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Persian Kit's Jalali post links are the ones Parsi Date gave, byte for
 * byte, so a site switching keeps every link. Runs against the real Parsi
 * Date: composer test:integration:sources.
 *
 * @group sources
 */
class ParsiDateLinksTest extends WordPressIntegrationTestCase
{
    use BootsDateConversion;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('parsidate')) {
            $this->markTestSkipped('Parsi Date is not loaded.');
        }

        update_option('timezone_string', 'Asia/Tehran');
        $this->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function edgeDates(): array
    {
        return [
            // 30 Esfand of the leap years 1403 and 1399.
            '30 Esfand 1403'          => ['2025-03-20 10:00:00'],
            '30 Esfand 1399'          => ['2021-03-20 10:00:00'],
            '1 Farvardin 1404'        => ['2025-03-21 00:00:00'],
            'Tehran midnight'         => ['2024-08-02 00:00:00'],
            'A second to midnight'    => ['2024-08-01 23:59:59'],
            '31 Shahrivar 1403'       => ['2024-09-21 10:00:00'],
            '1 Mehr 1403'             => ['2024-09-22 00:30:00'],
            'Before Tehran dropped DST' => ['2022-06-01 23:30:00'],
        ];
    }

    /**
     * @dataProvider edgeDates
     */
    public function test_post_links_match_parsi_date(string $date): void
    {
        $postId = self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => $date,
            'post_name'   => 'my-post',
        ]);

        // Persian Kit's Jalali links are off by default, so this is Parsi Date's.
        $parsiDate = get_permalink($postId);
        $this->assertDoesNotMatchRegularExpression('#/20\d\d/#', $parsiDate);

        remove_all_filters('post_link');
        $this->bootDateConversionWith(['jalali_permalinks' => true]);

        $this->assertSame($parsiDate, get_permalink($postId));
    }

    public function test_the_calendars_agree_on_every_day(): void
    {
        $timezone = wp_timezone();
        $day = new \DateTimeImmutable('1921-03-21 12:00:00', $timezone);
        $end = new \DateTimeImmutable('2094-03-20 12:00:00', $timezone);
        $disagreements = [];

        while ($day <= $end) {
            $ours = JalaliPeriod::fromGregorian($day);
            $theirs = parsidate('Y-m-d', $day->format('Y-m-d H:i:s'), 'eng');
            $expected = sprintf('%04d-%02d-%02d', $ours['jy'], $ours['jm'], $ours['jd']);

            if ($theirs !== $expected) {
                $disagreements[] = $day->format('Y-m-d') . ": {$theirs} vs {$expected}";
            }

            $day = $day->modify('+1 day');
        }

        $this->assertSame([], array_slice($disagreements, 0, 20));
    }
}
