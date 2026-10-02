<?php

namespace PersianKit\Tests\Unit\Multilingual;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Multilingual\PolylangModule;
use PersianKit\Modules\Multilingual\WpmlModule;
use PHPUnit\Framework\TestCase;

class MultilingualModulesTest extends TestCase
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

    /**
     * @return array<string, array{class-string<WpmlModule|PolylangModule>, string}>
     */
    public static function modules(): array
    {
        return [
            'WPML'     => [WpmlModule::class, 'wpml'],
            'Polylang' => [PolylangModule::class, 'polylang'],
        ];
    }

    /**
     * @dataProvider modules
     * @param class-string<WpmlModule|PolylangModule> $module
     */
    public function test_each_is_a_compatibility_integration_on_by_default(string $module, string $key): void
    {
        $this->assertSame($key, $module::key());
        $this->assertSame('compat', $module::category());
        $this->assertSame(['enabled' => true], $module::defaults());
        $this->assertStringContainsString("each page's language", $module::description());
    }

    public function test_polylang_links_to_wordpress_org_and_wpml_to_its_website(): void
    {
        $polylang = (new PolylangModule(Mockery::mock(SettingsManager::class)))->requiredPlugins()[0];
        $wpml = (new WpmlModule(Mockery::mock(SettingsManager::class)))->requiredPlugins()[0];

        $this->assertSame('polylang', $polylang['slug']);
        $this->assertArrayNotHasKey('url', $polylang);
        $this->assertSame('https://wpml.org/', $wpml['url']);
        $this->assertArrayNotHasKey('slug', $wpml);
    }

    /**
     * @dataProvider modules
     * @param class-string<WpmlModule|PolylangModule> $module
     */
    public function test_booting_does_nothing_as_the_language_is_followed_from_the_start(string $module): void
    {
        $container = Mockery::mock(ServiceContainer::class);
        $container->shouldNotReceive('get', 'register');
        $instance = new $module(Mockery::mock(SettingsManager::class));

        $instance->register($container);
        $instance->boot($container);
        $instance->bootDisabled($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider modules
     * @param class-string<WpmlModule|PolylangModule> $module
     */
    public function test_it_is_unavailable_until_its_plugin_is_active(string $module): void
    {
        $instance = new $module(Mockery::mock(SettingsManager::class));

        $this->assertFalse($instance->isAvailable());
        $this->assertCount(1, $instance->inactivePlugins());
    }
}
