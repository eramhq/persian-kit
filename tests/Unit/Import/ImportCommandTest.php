<?php

namespace PersianKit\Tests\Unit\Import;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Service\Import\Cli\ImportCommand;
use PHPUnit\Framework\TestCase;

class ImportCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', $key)));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_status_map(): void
    {
        $this->assertSame(
            ['wc-pws-packaged' => 'wc-processing', 'wc-pws-courier' => 'wc-completed'],
            ImportCommand::parseStatusMap('wc-pws-packaged:processing, pws-courier : wc-completed,broken,:x')
        );
    }
}
