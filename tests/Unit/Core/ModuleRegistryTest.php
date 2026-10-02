<?php

namespace PersianKit\Tests\Unit\Core;

use PersianKit\Contracts\ModuleInterface;
use PersianKit\Core\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    public function test_every_module_implements_the_module_interface(): void
    {
        foreach (ModuleRegistry::MODULES as $moduleClass) {
            $this->assertTrue(is_subclass_of($moduleClass, ModuleInterface::class), $moduleClass);
        }
    }

    public function test_module_keys_are_unique(): void
    {
        $keys = array_map(static fn (string $moduleClass): string => $moduleClass::key(), ModuleRegistry::MODULES);

        $this->assertCount(13, $keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
    }
}
