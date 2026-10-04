<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsFieldUsage;
use PersianKit\Tests\Unit\Forms\Support\FakeGravityField;
use PHPUnit\Framework\TestCase;

class GravityFormsFieldUsageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \GFAPI::$forms = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_changes_to_forms_clear_the_list(): void
    {
        $usage = new GravityFormsFieldUsage();
        $usage->register();

        foreach (['gform_after_save_form', 'gform_post_form_trashed', 'gform_post_form_restored', 'gform_after_delete_form', 'gform_post_form_duplicated', 'gform_forms_post_import'] as $hook) {
            $this->assertNotFalse(has_action($hook, [$usage, 'clear']), $hook);
        }
    }

    public function test_it_lists_the_forms_with_iranian_fields_and_caches_them(): void
    {
        \GFAPI::$forms = [
            ['id' => 2, 'title' => 'Contact', 'fields' => [new FakeGravityField(['type' => 'text'])]],
            ['id' => 3, 'title' => 'Sign up', 'fields' => [new FakeGravityField(['type' => 'email']), new FakeGravityField(['type' => 'persian_kit_national_id'])]],
        ];
        Functions\when('get_transient')->justReturn(false);
        Functions\expect('set_transient')->once()->with(GravityFormsFieldUsage::TRANSIENT, [['id' => 3, 'title' => 'Sign up']], WEEK_IN_SECONDS);

        $this->assertSame([['id' => 3, 'title' => 'Sign up']], (new GravityFormsFieldUsage())->formsUsingFields());
    }

    public function test_the_cached_list_is_used(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 3, 'title' => 'Sign up']]);
        Functions\expect('set_transient')->never();

        $this->assertSame([['id' => 3, 'title' => 'Sign up']], (new GravityFormsFieldUsage())->formsUsingFields());
    }

    public function test_fields_saved_as_arrays_count_too(): void
    {
        $this->assertTrue(GravityFormsFieldUsage::usesFields(['fields' => [['type' => 'persian_kit_iban']]]));
        $this->assertFalse(GravityFormsFieldUsage::usesFields(['fields' => [['type' => 'text']]]));
        $this->assertFalse(GravityFormsFieldUsage::usesFields([]));
    }
}
