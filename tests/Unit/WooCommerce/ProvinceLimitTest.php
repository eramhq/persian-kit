<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\ProvinceLimit;
use PHPUnit\Framework\TestCase;

class ProvinceLimitTest extends TestCase
{
    private const IR = ['THR' => 'Tehran', 'ABZ' => 'Alborz', 'ESF' => 'Isfahan'];

    private string $requestUri = '';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs([
            'is_admin'           => false,
            'wp_doing_cron'      => false,
            'rest_get_url_prefix' => 'wp-json',
            'wp_parse_url'       => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'wp_unslash'         => static fn (mixed $value): mixed => $value,
            'current_filter'     => 'woocommerce_customer_get_billing_state',
            'wc_strtoupper'      => static fn (string $value): string => mb_strtoupper($value),
        ]);
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();

        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $_SERVER['REQUEST_URI'] = '/checkout/';
    }

    protected function tearDown(): void
    {
        $_SERVER['REQUEST_URI'] = $this->requestUri;
        unset($_GET['rest_route']);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_storefront_requests_list_only_the_chosen_provinces(): void
    {
        $states = (new ProvinceLimit(['THR', 'ABZ']))->limitStates(['IR' => self::IR, 'DE' => ['DE-HE' => 'Hesse']]);

        $this->assertSame(['THR' => 'Tehran', 'ABZ' => 'Alborz'], $states['IR']);
        $this->assertSame(['DE-HE' => 'Hesse'], $states['DE'], 'other countries are untouched');
    }

    public function test_the_store_api_is_a_storefront_request(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->assertSame(['THR'], array_keys((new ProvinceLimit(['THR']))->limitStates(['IR' => self::IR])['IR']));

        $_SERVER['REQUEST_URI'] = '/';
        $_GET['rest_route'] = '/wc/store/v1/cart';
        $this->assertSame(['THR'], array_keys((new ProvinceLimit(['THR']))->limitStates(['IR' => self::IR])['IR']));
    }

    /**
     * @dataProvider adminRequests
     */
    public function test_the_admin_cron_cli_and_other_rest_routes_keep_every_province(callable $arrange): void
    {
        $arrange();

        $this->assertSame(self::IR, (new ProvinceLimit(['THR']))->limitStates(['IR' => self::IR])['IR']);
    }

    /**
     * @return array<string, array{callable}>
     */
    public static function adminRequests(): array
    {
        return [
            'admin'          => [static fn () => Functions\when('is_admin')->justReturn(true)],
            'cron'           => [static fn () => Functions\when('wp_doing_cron')->justReturn(true)],
            'wc/v3 REST'     => [static function (): void {
                $_SERVER['REQUEST_URI'] = '/wp-json/wc/v3/orders';
            }],
            'plain REST'     => [static function (): void {
                $_SERVER['REQUEST_URI'] = '/?rest_route=/wp/v2/users';
                $_GET['rest_route'] = '/wp/v2/users';
            }],
            'REST index'     => [static function (): void {
                $_SERVER['REQUEST_URI'] = '/wp-json';
            }],
        ];
    }

    public function test_codes_that_match_no_province_leave_the_list_whole(): void
    {
        $this->assertSame(self::IR, (new ProvinceLimit(['XXX']))->limitStates(['IR' => self::IR])['IR']);
    }

    public function test_a_list_without_iran_is_untouched(): void
    {
        $this->assertSame(['DE' => ['DE-HE' => 'Hesse']], (new ProvinceLimit(['THR']))->limitStates(['DE' => ['DE-HE' => 'Hesse']]));
        $this->assertNull((new ProvinceLimit(['THR']))->limitStates(null));
    }

    public function test_a_past_address_in_a_province_no_longer_listed_shows_its_name(): void
    {
        $limit = new ProvinceLimit(['THR']);
        $limit->limitStates(['IR' => self::IR]);

        $replacements = $limit->restoreStateName(
            ['{state}' => 'ESF', '{state_upper}' => 'ESF', '{state_code}' => 'ESF'],
            ['country' => 'IR', 'state' => 'ESF']
        );

        $this->assertSame(['{state}' => 'Isfahan', '{state_upper}' => 'ISFAHAN', '{state_code}' => 'ESF'], $replacements);
    }

    public function test_listed_provinces_and_other_countries_keep_their_replacements(): void
    {
        $limit = new ProvinceLimit(['THR']);
        $limit->limitStates(['IR' => self::IR]);

        $listed = ['{state}' => 'Tehran', '{state_upper}' => 'TEHRAN'];
        $this->assertSame($listed, $limit->restoreStateName($listed, ['country' => 'IR', 'state' => 'THR']));

        $german = ['{state}' => 'ESF', '{state_upper}' => 'ESF'];
        $this->assertSame($german, $limit->restoreStateName($german, ['country' => 'DE', 'state' => 'ESF']));
    }

    public function test_with_one_province_an_iranian_address_without_one_starts_with_it(): void
    {
        $limit = new ProvinceLimit(['THR']);
        $limit->limitStates(['IR' => self::IR]);
        $customer = new \WC_Customer(['billing_country' => 'IR', 'shipping_country' => 'DE']);

        $this->assertSame('THR', $limit->fillSingleProvince('', $customer));
        $this->assertSame('THR', $limit->fillSingleProvince('DE-HE', $customer), 'a state left from another country');
        $this->assertSame('ESF', $limit->fillSingleProvince('ESF', $customer), 'a saved province is kept');

        Functions\when('current_filter')->justReturn('woocommerce_customer_get_shipping_state');
        $this->assertSame('', $limit->fillSingleProvince('', $customer), 'the shipping address is in Germany');
    }

    public function test_the_province_is_not_filled_with_more_than_one_or_in_the_admin(): void
    {
        $customer = new \WC_Customer(['billing_country' => 'IR']);

        $this->assertSame('', (new ProvinceLimit(['THR', 'ABZ']))->fillSingleProvince('', $customer));

        Functions\when('is_admin')->justReturn(true);
        $this->assertSame('', (new ProvinceLimit(['THR']))->fillSingleProvince('', $customer));
    }

    public function test_sanitize_codes_keeps_known_codes_once(): void
    {
        $this->assertSame(
            ['THR', 'ESF'],
            ProvinceLimit::sanitizeCodes(['', 'THR', ' esf ', 'THR', 'XXX', 7, ['ABZ']], self::IR)
        );
        $this->assertSame([], ProvinceLimit::sanitizeCodes('THR', self::IR));
    }

    public function test_without_woocommerce_any_three_letter_code_passes(): void
    {
        $this->assertSame(['THR', 'ZZZ'], ProvinceLimit::sanitizeCodes(['THR', 'zzz', 'TOOLONG', 'T1R', ''], []));
    }
}
