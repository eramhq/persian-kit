<?php

namespace PersianKit\Tests\Unit\DigitConversion;

use Brain\Monkey;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Tests\Unit\Support\FailsPcre;
use PHPUnit\Framework\TestCase;

class DigitConversionModuleTest extends TestCase
{
    use FailsPcre;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_convert_content_converts_text_but_not_markup(): void
    {
        $this->assertSame(
            '<p class="c1">سال ۱۴۰۳</p>',
            DigitConversionModule::convertContent('<p class="c1">سال 1403</p>')
        );
    }

    public function test_convert_content_returns_input_when_segmentation_fails(): void
    {
        $html = self::unsegmentableHtml();

        $this->assertSame($html, self::withFailingPcre(fn () => DigitConversionModule::convertContent($html)));
    }
}
