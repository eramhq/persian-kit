<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\AcfDateFields;
use PersianKit\Modules\Forms\Cf7DateField;
use PersianKit\Modules\Forms\Cf7InputNormalizer;
use PersianKit\Modules\Forms\Cf7IranianFields;
use PersianKit\Modules\Forms\FormsModule;
use PHPUnit\Framework\TestCase;

class FormsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Contact Form 7 counts as active when its version and form tag API exist.
        if (!defined('WPCF7_VERSION')) {
            define('WPCF7_VERSION', '6.1.7');
        }
        Functions\when('wpcf7_add_form_tag')->justReturn(null);
        // ACF counts as active when its field type API exists.
        Functions\when('acf_get_field_type')->justReturn(null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_turn_on_contact_form_7_and_acf(): void
    {
        $this->assertSame(['enabled' => true, 'cf7' => true, 'acf' => true], FormsModule::defaults());
    }

    public function test_boot_registers_the_contact_form_7_and_acf_services(): void
    {
        $this->assertSame([
            Cf7DateField::class,
            Cf7InputNormalizer::class,
            Cf7IranianFields::class,
            AcfDateFields::class,
        ], $this->bootAndListFetched());
    }

    public function test_each_option_turns_its_services_off(): void
    {
        $this->assertSame([AcfDateFields::class], $this->bootAndListFetched(['cf7' => false]));
        $this->assertSame([
            Cf7DateField::class,
            Cf7InputNormalizer::class,
            Cf7IranianFields::class,
        ], $this->bootAndListFetched(['acf' => false]));
    }

    public function test_sanitize_settings_stores_booleans(): void
    {
        $this->assertSame(
            ['enabled' => true, 'cf7' => false, 'acf' => true],
            $this->makeModule()->sanitizeSettings(['enabled' => '1', 'cf7' => '0', 'acf' => '1', 'unknown' => '1'])
        );
    }

    public function test_emails_show_jalali_dates_while_date_conversion_is_on(): void
    {
        foreach ([true, false] as $dateConversion) {
            $factories = [];
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, &$container) {
                $factories[$id] = $factory;

                return $container;
            });

            $this->makeModule([], $dateConversion)->register($container);
            $field = $factories[Cf7DateField::class]($container);
            $acf = $factories[AcfDateFields::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliMail)->call($field));
            $this->assertSame($dateConversion, (fn () => $this->jalaliValues)->call($acf));
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function makeModule(array $settings = [], bool $dateConversion = true): FormsModule
    {
        $merged = array_replace(FormsModule::defaults(), $settings);
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            function (string $module, ?string $key = null, mixed $default = null) use ($merged, $dateConversion) {
                if ($module === 'date_conversion') {
                    return $key === 'enabled' ? $dateConversion : $default;
                }

                return $key === null ? $merged : ($merged[$key] ?? $default);
            }
        );

        return new FormsModule($manager);
    }

    /**
     * @param array<string, mixed> $settings
     * @return list<string> Service ids fetched from the container, in order.
     */
    private function bootAndListFetched(array $settings = []): array
    {
        $fetched = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$fetched) {
            $fetched[] = $id;
            $service = Mockery::mock($id);
            $service->shouldReceive('register')->once();

            return $service;
        });

        $this->makeModule($settings)->boot($container);

        return $fetched;
    }
}
