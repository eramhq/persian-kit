<?php

namespace PersianKit\Tests\Unit\Container;

use PersianKit\Container\ServiceContainer;
use PersianKit\Container\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    public function test_get_throws_for_an_unknown_id(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('persian_kit_test_missing');

        ServiceContainer::getInstance()->get('persian_kit_test_missing');
    }

    public function test_not_found_is_a_runtime_exception(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, ServiceNotFoundException::forId('x'));
    }

    public function test_get_builds_a_registered_service_once(): void
    {
        $container = ServiceContainer::getInstance();
        $built = 0;

        $container->register('persian_kit_test_service', function () use (&$built) {
            $built++;

            return new \stdClass();
        });

        $this->assertTrue($container->has('persian_kit_test_service'));
        $this->assertSame($container->get('persian_kit_test_service'), $container->get('persian_kit_test_service'));
        $this->assertSame(1, $built);
    }
}
