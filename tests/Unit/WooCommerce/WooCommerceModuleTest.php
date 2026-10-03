<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\WooCommerce\CallForPrice;
use PersianKit\Modules\WooCommerce\CheckoutInputNormalizer;
use PersianKit\Modules\WooCommerce\CheckoutValidator;
use PersianKit\Modules\WooCommerce\CityField;
use PersianKit\Modules\WooCommerce\IranianCurrencies;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PersianKit\Modules\WooCommerce\OrderNumberInput;
use PersianKit\Modules\WooCommerce\PersianEmailFont;
use PersianKit\Modules\WooCommerce\ProvinceLimit;
use PersianKit\Modules\WooCommerce\SchemaPrices;
use PersianKit\Modules\WooCommerce\ShortCheckout;
use PersianKit\Modules\WooCommerce\WooAdminDateFields;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\WooCommerce\WooDateDisplayFilter;
use PersianKit\Modules\WooCommerce\WooOrderMonthFilter;
use PersianKit\Modules\WooCommerce\WooPostedDateNormalizer;
use PHPUnit\Framework\TestCase;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;

class WooCommerceModuleTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        // WooCommerce counts as active when wc_get_orders() exists.
        Functions\when('wc_get_orders')->justReturn([]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_boot_outside_admin_registers_front_end_services_only(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
            PersianEmailFont::class,
        ], $this->bootAndListFetched());
    }

    public function test_boot_in_admin_registers_admin_services(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
            PersianEmailFont::class,
            WooOrderMonthFilter::class,
            WooAdminDateFields::class,
            WooPostedDateNormalizer::class,
        ], $this->bootAndListFetched());
    }

    public function test_checkout_options_turn_their_services_on_and_off(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            NationalIdField::class,
            CityField::class,
            PersianEmailFont::class,
        ], $this->bootAndListFetched(['checkout_normalize' => false, 'checkout_validate' => false, 'city_select' => true]));
    }

    public function test_admins_whose_language_is_not_persian_get_gregorian_order_screens(): void
    {
        $this->inLanguage('en_US', true);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
            PersianEmailFont::class,
            WooPostedDateNormalizer::class,
        ], $this->bootAndListFetched());
    }

    public function test_admin_date_fields_have_their_own_option(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([
            SchemaPrices::class,
            WooDateDisplayFilter::class,
            CheckoutInputNormalizer::class,
            OrderNumberInput::class,
            CheckoutValidator::class,
            NationalIdField::class,
            PersianEmailFont::class,
        ], $this->bootAndListFetched(['dates_admin' => false]));
    }

    public function test_schema_prices_stay_while_the_module_is_off(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame([SchemaPrices::class], $this->bootAndListFetched([], 'bootDisabled'));
    }

    public function test_the_currencies_are_added_when_services_register_whether_the_module_is_on_or_off(): void
    {
        $currencies = Mockery::mock(IranianCurrencies::class);
        $currencies->shouldReceive('register')->once();
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('register');
        $container->shouldReceive('get')->once()->with(IranianCurrencies::class)->andReturn($currencies);

        $this->makeModule(['enabled' => false])->register($container);
        $this->addToAssertionCount(1);
    }

    public function test_inactive_plugins_names_woocommerce_when_it_is_missing(): void
    {
        Functions\when('__')->returnArg();
        $this->assertSame('commerce', WooCommerceModule::category());
        $this->assertSame([], $this->makeModule()->inactivePlugins());
        $this->assertTrue($this->makeModule()->isAvailable());

        $withoutWooCommerce = new class (Mockery::mock(SettingsManager::class)) extends WooCommerceModule {
            protected function supportsWooCommerce(): bool
            {
                return false;
            }
        };

        $this->assertSame(['WooCommerce'], $withoutWooCommerce->inactivePlugins());
        $this->assertFalse($withoutWooCommerce->isAvailable());
    }

    public function test_sanitize_settings_keeps_known_national_id_modes_only(): void
    {
        $module = $this->makeModule();

        $this->assertSame([
            'enabled'                  => true,
            'checkout_normalize'       => false,
            'checkout_validate'        => true,
            'national_id'              => 'required',
            'city_select'              => false,
            'allowed_states'           => [],
            'short_checkout'           => false,
            'dates_admin'              => true,
            'call_for_price'           => false,
            'call_for_price_text'      => '',
            'call_for_price_list_text' => '',
            'call_for_price_link'      => '',
            'email_font'               => false,
        ], $module->sanitizeSettings(['enabled' => '1', 'checkout_normalize' => '0', 'checkout_validate' => '1', 'national_id' => 'required', 'dates_admin' => '1']));

        $this->assertSame('off', $module->sanitizeSettings(['national_id' => 'always'])['national_id']);
    }

    public function test_new_checkout_settings_default_to_fixing_and_checking_only(): void
    {
        $this->assertSame([
            'enabled'                  => true,
            'checkout_normalize'       => true,
            'checkout_validate'        => true,
            'national_id'              => 'off',
            'city_select'              => false,
            'allowed_states'           => [],
            'short_checkout'           => false,
            'dates_admin'              => true,
            'call_for_price'           => false,
            'call_for_price_text'      => '',
            'call_for_price_list_text' => '',
            'call_for_price_link'      => '',
            'email_font'               => true,
        ], WooCommerceModule::defaults());
    }

    public function test_sanitize_settings_keeps_valid_province_codes_once(): void
    {
        $module = $this->makeModule();

        // The hidden field sends '' with the ticked boxes.
        $this->assertSame(['THR', 'ABZ'], $module->sanitizeSettings(['allowed_states' => ['', 'THR', 'abz', 'THR', 'nope']])['allowed_states']);
        $this->assertSame([], $module->sanitizeSettings(['allowed_states' => ['']])['allowed_states']);
        $this->assertSame([], $module->sanitizeSettings(['allowed_states' => 'THR'])['allowed_states']);
    }

    public function test_chosen_provinces_turn_the_province_limit_on(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertNotContains(ProvinceLimit::class, $this->bootAndListFetched());
        $this->assertContains(ProvinceLimit::class, $this->bootAndListFetched(['allowed_states' => ['THR']]));
    }

    public function test_the_shorter_checkout_has_its_own_option(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertNotContains(ShortCheckout::class, $this->bootAndListFetched());
        $this->assertContains(ShortCheckout::class, $this->bootAndListFetched(['short_checkout' => true]));
        $this->assertNotContains(ShortCheckout::class, $this->bootAndListFetched(['short_checkout' => true], 'bootDisabled'));
        $this->assertTrue($this->makeModule()->sanitizeSettings(['short_checkout' => '1'])['short_checkout']);
        $this->assertFalse($this->makeModule()->sanitizeSettings(['short_checkout' => '0'])['short_checkout']);
    }

    public function test_call_for_price_has_its_own_option(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertNotContains(CallForPrice::class, $this->bootAndListFetched());
        $this->assertContains(CallForPrice::class, $this->bootAndListFetched(['call_for_price' => true]));
        $this->assertNotContains(CallForPrice::class, $this->bootAndListFetched(['call_for_price' => true], 'bootDisabled'));
    }

    public function test_the_persian_email_font_is_on_by_default_also_in_the_admin(): void
    {
        foreach ([false, true] as $admin) {
            Functions\when('is_admin')->justReturn($admin);

            $this->assertContains(PersianEmailFont::class, $this->bootAndListFetched());
            $this->assertNotContains(PersianEmailFont::class, $this->bootAndListFetched(['email_font' => false]));
            $this->assertNotContains(PersianEmailFont::class, $this->bootAndListFetched([], 'bootDisabled'));
        }

        $this->assertTrue($this->makeModule()->sanitizeSettings(['email_font' => '1'])['email_font']);
        $this->assertFalse($this->makeModule()->sanitizeSettings(['email_font' => '0'])['email_font']);
    }

    public function test_sanitize_settings_cleans_the_call_for_price_texts_and_link(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn (string $text): string => trim(strip_tags($text)));
        Functions\when('wp_strip_all_tags')->alias('strip_tags');
        Functions\when('esc_url_raw')->returnArg();

        $values = $this->makeModule()->sanitizeSettings([
            'call_for_price'           => '1',
            'call_for_price_text'      => '<strong>تماس بگیرید</strong>',
            'call_for_price_list_text' => '',
            'call_for_price_link'      => '۰۲۱ ۱۲۳۴ ۵۶۷۸',
        ]);

        $this->assertTrue($values['call_for_price']);
        $this->assertSame('تماس بگیرید', $values['call_for_price_text']);
        $this->assertSame('', $values['call_for_price_list_text']);
        $this->assertSame('021 1234 5678', $values['call_for_price_link']);
        $this->assertSame('', $this->makeModule()->sanitizeSettings(['call_for_price_link' => 'javascript:alert(1)'])['call_for_price_link']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = []): WooCommerceModule
    {
        $merged = array_replace(WooCommerceModule::defaults(), $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            fn (string $module, ?string $key = null, mixed $default = null) => $key === null ? $merged : ($merged[$key] ?? $default)
        );

        return new WooCommerceModule($manager);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string> Service ids fetched from the container, in order.
     */
    private function bootAndListFetched(array $settings = [], string $method = 'boot'): array
    {
        $fetched = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$fetched) {
            $fetched[] = $id;
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            return $service;
        });

        $this->makeModule($settings)->{$method}($container);

        return $fetched;
    }
}
