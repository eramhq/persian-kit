<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsAddress;
use PersianKit\Modules\Forms\GravityFormsInputNormalizer;
use PersianKit\Modules\Forms\IranianFieldTypes;
use PersianKit\Tests\Unit\Forms\Support\FakeGravityField;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class GravityFormsAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
        \GF_Fields::$fields = [];
    }

    protected function tearDown(): void
    {
        \GF_Fields::$fields = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_the_type_stays_while_the_integration_is_off_and_only_the_postcode_check_stops(): void
    {
        $address = new GravityFormsAddress();
        $address->registerFallback();
        $this->assertNotFalse(has_filter('gform_address_types', [$address, 'addType']));
        $this->assertFalse(has_filter('gform_field_validation', [$address, 'validatePostcode']));

        $address->register();
        $this->assertNotFalse(has_filter('gform_field_validation', [$address, 'validatePostcode']));
    }

    public function test_iran_has_its_provinces_in_persian_and_iran_as_the_country(): void
    {
        $types = (new GravityFormsAddress())->addType(['international' => ['label' => 'International']]);
        $iran = $types['iran'];

        $this->assertSame(['Iran', 'Postcode', 'Province', 'IR'], [$iran['label'], $iran['zip_label'], $iran['state_label'], $iran['country']]);
        $this->assertSame('', $iran['states'][0]);
        $this->assertCount(32, $iran['states']);
        $this->assertContains('تهران', $iran['states']);
        $this->assertSame('آذربایجان شرقی', $iran['states'][1]);
        $this->assertSame('یزد', end($iran['states']));
        $this->assertArrayHasKey('international', $types);
    }

    public function test_another_plugins_iran_type_is_kept(): void
    {
        $theirs = ['iran' => ['label' => 'ایران']];

        $this->assertSame($theirs, (new GravityFormsAddress())->addType($theirs));
    }

    public function test_gravity_forms_before_3_0_3_gets_its_own_name_for_iran(): void
    {
        \GF_Fields::register(new class () extends \GF_Field {
            public $type = 'address';

            /** @return array<string, string> */
            public function get_default_countries(): array
            {
                return ['IQ' => 'Iraq', 'IR' => 'ایران'];
            }
        });

        $this->assertSame('ایران', (new GravityFormsAddress())->addType([])['iran']['country']);
    }

    public function test_an_iran_postcode_must_be_valid(): void
    {
        $address = new GravityFormsAddress();
        $iran = new FakeGravityField(['id' => 3, 'type' => 'address', 'addressType' => 'iran']);
        $valid = ['is_valid' => true, 'message' => ''];

        $this->assertSame(
            ['is_valid' => false, 'message' => IranianFieldTypes::message('postcode_ir')],
            $address->validatePostcode($valid, ['3.1' => 'Valiasr', '3.5' => '123'], [], $iran)
        );
        $this->assertSame($valid, $address->validatePostcode($valid, ['3.5' => '۱۲۳۴۵-۶۷۸۹۰'], [], $iran));
        $this->assertSame($valid, $address->validatePostcode($valid, ['3.5' => ''], [], $iran));

        // Other address types, and checks Gravity Forms already failed.
        $international = new FakeGravityField(['id' => 3, 'type' => 'address', 'addressType' => 'international']);
        $this->assertSame($valid, $address->validatePostcode($valid, ['3.5' => '123'], [], $international));
        $required = ['is_valid' => false, 'message' => 'This field is required.'];
        $this->assertSame($required, $address->validatePostcode($required, ['3.5' => '123'], [], $iran));
    }

    public function test_an_iran_postcode_is_saved_as_ten_english_digits(): void
    {
        $form = ['id' => 1, 'fields' => [
            new FakeGravityField(['id' => 3, 'type' => 'address', 'addressType' => 'iran']),
            new FakeGravityField(['id' => 4, 'type' => 'address', 'addressType' => 'international']),
        ]];

        $this->assertSame(
            ['input_3_5' => '1234567890', 'input_4_5' => '12345-67890'],
            (new GravityFormsInputNormalizer())->normalizePost(['input_3_5' => '۱۲۳۴۵-۶۷۸۹۰', 'input_4_5' => '۱۲۳۴۵-۶۷۸۹۰'], $form)
        );
    }
}
