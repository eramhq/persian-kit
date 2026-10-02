<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;

/**
 * What an SEO plugin prints for search engines on a page: its JSON-LD, Open
 * Graph tags and sitemaps, with Persian Kit set to change the most dates
 * and digits it can.
 */
trait ReadsSeoOutput
{
    use BootsDateConversion;

    /**
     * Jalali dates everywhere (global conversion) and Persian digits in
     * dates, counts and prices. Hooks added here are removed after the test.
     */
    private function convertEverything(): void
    {
        $this->bootDateConversionWith(['global_conversion' => true]);

        $settings = new SettingsManager();
        update_option('persian_kit_settings', array_replace(get_option('persian_kit_settings'), [
            DigitConversionModule::key() => ['enabled' => true, 'dates' => true, 'numbers' => true, 'prices' => true],
        ]));
        $settings->registerDefaults(DigitConversionModule::key(), DigitConversionModule::defaults());
        (new DigitConversionModule($settings))->boot(Bootstrap::container());
    }

    private function head(string $url): string
    {
        $this->go_to($url);

        ob_start();
        do_action('wp_head');

        return (string) ob_get_clean();
    }

    /**
     * The JSON-LD in the script with this class, as an array.
     *
     * @return array<mixed>
     */
    private function jsonLd(string $html, string $class): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json" class="' . preg_quote($class, '#') . '">(.*?)</script>#s', $html, $match), "no $class script");

        $data = json_decode($match[1], true);
        $this->assertIsArray($data);

        return $data;
    }

    /**
     * @return list<string> The content of each meta tag with this property.
     */
    private function meta(string $html, string $property): array
    {
        preg_match_all('#<meta (?:property|name)="' . preg_quote($property, '#') . '" content="([^"]*)"#', $html, $matches);

        return $matches[1];
    }

    /**
     * Every value under $node with one of these keys.
     *
     * @param array<mixed> $node
     * @param list<string> $keys
     * @return list<mixed>
     */
    private function valuesOf(array $node, array $keys): array
    {
        $values = [];
        foreach ($node as $key => $value) {
            if (in_array($key, $keys, true)) {
                $values[] = $value;
            }
            if (is_array($value)) {
                $values = array_merge($values, $this->valuesOf($value, $keys));
            }
        }

        return $values;
    }

    private function assertMachineDate(mixed $date): void
    {
        $this->assertIsString($date);
        $this->assertMatchesRegularExpression('/^20\d\d-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $date);
        // No Jalali year (13xx, 14xx) and no Persian or Arabic digit.
        $this->assertDoesNotMatchRegularExpression('/(?:^|\D)1[34]\d\d-\d\d|[۰-۹٠-٩]/u', $date);
    }

    /**
     * A product at 120 thousand toman.
     */
    private function productInThousandToman(): int
    {
        update_option('woocommerce_currency', 'IRHT');

        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120');
        $product->set_date_created('2026-04-05 10:00:00');

        return $product->save();
    }

    private function postOnFarvardin16(): int
    {
        return self::factory()->post->create([
            'post_title'    => 'Hello',
            'post_status'   => 'publish',
            'post_date'     => '2026-04-05 13:30:00',
            'post_date_gmt' => '2026-04-05 10:00:00',
        ]);
    }
}
