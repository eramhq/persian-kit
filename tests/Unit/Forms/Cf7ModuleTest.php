<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\Cf7DateField;
use PersianKit\Modules\Forms\Cf7FieldUsage;
use PersianKit\Modules\Forms\Cf7InputNormalizer;
use PersianKit\Modules\Forms\Cf7IranianFields;
use PersianKit\Modules\Forms\Cf7Module;
use PersianKit\Modules\Forms\Cf7TagGenerator;
use PHPUnit\Framework\TestCase;

class Cf7ModuleTest extends TestCase
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
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_is_a_form_integration_on_by_default(): void
    {
        $this->assertSame('cf7', Cf7Module::key());
        $this->assertSame('forms', Cf7Module::category());
        $this->assertSame(['enabled' => true], Cf7Module::defaults());
    }

    public function test_boot_registers_the_fields_the_date_picker_and_the_usage_cache(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame([
            Cf7DateField::class . '::register',
            Cf7InputNormalizer::class . '::register',
            Cf7IranianFields::class . '::register',
            Cf7FieldUsage::class . '::register',
        ], $this->listCalls(fn (Cf7Module $module, ServiceContainer $container) => $module->boot($container)));
    }

    public function test_in_the_admin_it_adds_the_form_editor_buttons(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $calls = $this->listCalls(fn (Cf7Module $module, ServiceContainer $container) => $module->boot($container));

        $this->assertSame(Cf7TagGenerator::class . '::register', end($calls));
    }

    public function test_turned_off_it_keeps_the_fields_as_plain_inputs_only(): void
    {
        $this->assertSame([
            Cf7IranianFields::class . '::registerFallback',
            Cf7FieldUsage::class . '::register',
        ], $this->listCalls(fn (Cf7Module $module, ServiceContainer $container) => $module->bootDisabled($container)));
    }

    public function test_it_is_available_while_contact_form_7_is_active(): void
    {
        $this->assertTrue($this->makeModule()->isAvailable());
        $this->assertSame([], $this->makeModule()->inactivePlugins());

        $without = new class (Mockery::mock(SettingsManager::class)) extends Cf7Module {
            protected function supportsContactForm7(): bool
            {
                return false;
            }
        };

        $this->assertFalse($without->isAvailable());
        $this->assertSame(AbstractModule::REASON_INACTIVE, $without->unavailableReason()['code'] ?? null);
        $this->assertSame(['Contact Form 7'], $without->inactivePlugins());
        $this->assertSame([], $without->formsUsingFields());
    }

    public function test_forms_using_the_fields_link_to_their_edit_screens(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 7, 'title' => 'Contact']]);
        Functions\when('admin_url')->alias(fn (string $path) => 'https://example.org/wp-admin/' . $path);

        $this->assertSame(
            [['title' => 'Contact', 'url' => 'https://example.org/wp-admin/admin.php?page=wpcf7&post=7&action=edit']],
            $this->makeModule()->formsUsingFields()
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

            $this->makeModule($dateConversion)->register($container);
            $field = $factories[Cf7DateField::class]($container);

            $this->assertSame($dateConversion, (fn () => $this->jalaliMail)->call($field));
        }
    }

    private function makeModule(bool $dateConversion = true): Cf7Module
    {
        $manager = Mockery::mock(SettingsManager::class);
        $manager->shouldReceive('module')->andReturnUsing(
            function (string $module, ?string $key = null, mixed $default = null) use ($dateConversion) {
                if ($module === 'date_conversion') {
                    return $key === 'enabled' ? $dateConversion : $default;
                }

                return $key === null ? Cf7Module::defaults() : (Cf7Module::defaults()[$key] ?? $default);
            }
        );

        return new Cf7Module($manager);
    }

    /**
     * @param callable(Cf7Module, ServiceContainer): void $boot
     * @return list<string> Service::method calls, in order.
     */
    private function listCalls(callable $boot): array
    {
        $calls = [];
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldReceive('get')->andReturnUsing(function (string $id) use (&$calls) {
            $service = Mockery::mock($id);
            foreach (['register', 'registerFallback'] as $method) {
                if (method_exists($id, $method)) {
                    $service->shouldReceive($method)->andReturnUsing(function () use (&$calls, $id, $method) {
                        $calls[] = $id . '::' . $method;
                    });
                }
            }

            return $service;
        });

        $boot($this->makeModule(), $container);

        return $calls;
    }
}
