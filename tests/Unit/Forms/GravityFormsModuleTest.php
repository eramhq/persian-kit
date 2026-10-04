<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\GravityFormsAddress;
use PersianKit\Modules\Forms\GravityFormsDateField;
use PersianKit\Modules\Forms\GravityFormsEntryDates;
use PersianKit\Modules\Forms\GravityFormsFieldUsage;
use PersianKit\Modules\Forms\GravityFormsInputNormalizer;
use PersianKit\Modules\Forms\GravityFormsIranianFields;
use PersianKit\Modules\Forms\GravityFormsModule;
use PHPUnit\Framework\TestCase;

class GravityFormsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_a_form_integration_on_by_default(): void
    {
        $this->assertSame('gravityforms', GravityFormsModule::key());
        $this->assertSame('forms', GravityFormsModule::category());
        $this->assertSame(['enabled' => true], GravityFormsModule::defaults());
    }

    public function test_boot_registers_the_dates_the_digits_the_fields_and_the_usage_cache(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            GravityFormsDateField::class . '::register',
            GravityFormsInputNormalizer::class . '::register',
            GravityFormsIranianFields::class . '::register',
            GravityFormsAddress::class . '::register',
            GravityFormsFieldUsage::class . '::register',
        ], $this->listCalls(fn (GravityFormsModule $module, ServiceContainer $container) => $module->boot($container)));
    }

    public function test_in_the_admin_entry_dates_are_jalali_while_date_conversion_is_on(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $this->assertContains(
            GravityFormsEntryDates::class . '::register',
            $this->listCalls(fn (GravityFormsModule $module, ServiceContainer $container) => $module->boot($container))
        );
        $this->assertNotContains(
            GravityFormsEntryDates::class . '::register',
            $this->listCalls(fn (GravityFormsModule $module, ServiceContainer $container) => $module->boot($container), false)
        );
    }

    public function test_turned_off_the_iranian_fields_and_addresses_stay_and_the_usage_list_stays_current(): void
    {
        $this->assertSame([
            GravityFormsIranianFields::class . '::registerFallback',
            GravityFormsAddress::class . '::registerFallback',
            GravityFormsFieldUsage::class . '::register',
        ], $this->listCalls(fn (GravityFormsModule $module, ServiceContainer $container) => $module->bootDisabled($container)));
    }

    public function test_forms_using_the_fields_link_to_their_edit_screens(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 12, 'title' => 'Contact']]);
        Functions\when('admin_url')->alias(fn (string $path) => 'https://example.org/wp-admin/' . $path);

        $this->assertSame(
            [['title' => 'Contact', 'url' => 'https://example.org/wp-admin/admin.php?page=gf_edit_forms&id=12']],
            $this->makeModule()->formsUsingFields()
        );
    }

    public function test_it_needs_gravity_forms_2_9_or_newer_from_its_own_website(): void
    {
        $plugin = $this->makeModule()->requiredPlugins()[0];

        $this->assertSame('Gravity Forms', $plugin['name']);
        $this->assertSame('https://www.gravityforms.com/', $plugin['url']);
        $this->assertArrayNotHasKey('slug', $plugin);
        $this->assertSame('2.9', $plugin['minVersion']);

        $without = new class (Mockery::mock(SettingsManager::class)) extends GravityFormsModule {
            protected function supportsGravityForms(): bool
            {
                return false;
            }
        };

        $this->assertFalse($without->isAvailable());
        $this->assertSame(AbstractModule::REASON_INACTIVE, $without->unavailableReason()['code'] ?? null);
        $this->assertSame([], $without->formsUsingFields());
    }

    public function test_entries_and_merge_tags_show_jalali_dates_while_date_conversion_is_on(): void
    {
        foreach ([true, false] as $dateConversion) {
            $factories = [];
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('register')->andReturnUsing(function (string $id, callable $factory) use (&$factories, &$container) {
                $factories[$id] = $factory;

                return $container;
            });

            $this->makeModule($dateConversion)->register($container);
            $field = $factories[GravityFormsDateField::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliDates)->call($field));
        }
    }

    protected function makeModule(bool $dateConversion = true): GravityFormsModule
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            function (string $module, ?string $key = null, mixed $default = null) use ($dateConversion) {
                if ($module === 'date_conversion') {
                    return $key === 'enabled' ? $dateConversion : $default;
                }

                return $key === null ? GravityFormsModule::defaults() : (GravityFormsModule::defaults()[$key] ?? $default);
            }
        );

        // Gravity Forms counts as active.
        return new class ($manager) extends GravityFormsModule {
            protected function supportsGravityForms(): bool
            {
                return true;
            }
        };
    }

    /**
     * @param callable(GravityFormsModule, ServiceContainer): void $boot
     * @return list<string> Service::method calls, in order.
     */
    protected function listCalls(callable $boot, bool $dateConversion = true): array
    {
        $calls = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$calls) {
            $service = Mockery::mock($id);
            foreach (['register', 'registerFallback'] as $method) {
                $service->shouldReceive($method)->andReturnUsing(function () use (&$calls, $id, $method) {
                    $calls[] = $id . '::' . $method;
                });
            }

            return $service;
        });

        $boot($this->makeModule($dateConversion), $container);

        return $calls;
    }
}
