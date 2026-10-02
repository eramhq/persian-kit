<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\Cf7TagGenerator;
use PHPUnit\Framework\TestCase;

class Cf7TagGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
        Functions\when('esc_html')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_waits_for_the_form_editor(): void
    {
        $generator = new Cf7TagGenerator();
        $generator->register();

        $this->assertSame(60, has_action('wpcf7_admin_init', [$generator, 'addTagGenerators']));
    }

    public function test_each_iranian_field_gets_a_button(): void
    {
        require_once __DIR__ . '/Support/FakeTagGenerator.php';
        \WPCF7_TagGenerator::$added = [];

        (new Cf7TagGenerator())->addTagGenerators();

        $this->assertSame(
            [
                'mobile_ir'   => 'Mobile number',
                'national_id' => 'National ID',
                'postcode_ir' => 'Postcode',
                'card_ir'     => 'Bank card number',
                'iban_ir'     => 'IBAN (Sheba)',
            ],
            array_map(static fn (array $panel): string => $panel['title'], \WPCF7_TagGenerator::$added)
        );
        $this->assertSame(['version' => '2'], \WPCF7_TagGenerator::$added['iban_ir']['options']);
    }

    public function test_the_dialog_offers_the_field_with_a_required_option(): void
    {
        require_once __DIR__ . '/Support/FakeTagGenerator.php';
        \WPCF7_TagGeneratorGenerator::$printed = [];

        ob_start();
        (new Cf7TagGenerator())->render(null, ['id' => 'national_id', 'content' => 'tag-generator-panel-national_id']);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('National ID field form-tag generator', $html);
        $this->assertSame(
            ['field_type', 'field_name', 'class_attr', 'default_value', 'insert_box_content', 'mail_tag_tip'],
            array_column(\WPCF7_TagGeneratorGenerator::$printed, 0)
        );
        $this->assertSame(
            ['with_required' => true, 'select_options' => ['national_id' => 'National ID']],
            \WPCF7_TagGeneratorGenerator::$printed[0][1]
        );
    }
}
