<?php

namespace PersianKit\Tests\Unit\Seo;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Seo\RankMathModule;
use PersianKit\Modules\Seo\RankMathPrices;
use PersianKit\Modules\Seo\YoastModule;
use PersianKit\Modules\Seo\YoastPrices;
use PHPUnit\Framework\TestCase;

class SeoModulesTest extends TestCase
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
     * @dataProvider modules
     * @param class-string<YoastModule|RankMathModule> $module
     */
    public function test_each_is_a_compatibility_integration_on_by_default(string $module, string $key, string $slug): void
    {
        $this->assertSame($key, $module::key());
        $this->assertSame('compat', $module::category());
        $this->assertSame(['enabled' => true], $module::defaults());
        $this->assertSame($slug, (new $module(Mockery::mock(SettingsManager::class)))->requiredPlugins()[0]['slug']);
        $this->assertStringContainsString('Gregorian', $module::description());
    }

    /**
     * @return array<string, array{class-string, string, string, class-string}>
     */
    public static function modules(): array
    {
        return [
            'Yoast SEO' => [YoastModule::class, 'yoast', 'wordpress-seo', YoastPrices::class],
            'Rank Math' => [RankMathModule::class, 'rank_math', 'seo-by-rank-math', RankMathPrices::class],
        ];
    }

    /**
     * @dataProvider modules
     * @param class-string<YoastModule|RankMathModule> $module
     * @param class-string $service
     */
    public function test_its_prices_are_hooked_whether_the_switch_is_on_or_off(string $module, string $key, string $slug, string $service): void
    {
        foreach (['boot', 'bootDisabled'] as $method) {
            $container = Mockery::mock(ServiceContainer::class);
            $container->shouldReceive('get')->once()->with($service)->andReturn(
                Mockery::mock($service)->shouldReceive('register')->once()->getMock()
            );

            (new $module(Mockery::mock(SettingsManager::class)))->{$method}($container);
        }

        $this->addToAssertionCount(2);
    }

    /**
     * @dataProvider modules
     * @param class-string<YoastModule|RankMathModule> $module
     */
    public function test_it_is_unavailable_until_its_plugin_is_active(string $module): void
    {
        $instance = new $module(Mockery::mock(SettingsManager::class));

        $this->assertFalse($instance->isAvailable());
        $this->assertCount(1, $instance->inactivePlugins());
    }
}
