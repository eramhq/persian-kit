<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\Cf7FieldUsage;
use PHPUnit\Framework\TestCase;

class Cf7FieldUsageTest extends TestCase
{
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

    public function test_it_finds_the_iranian_field_tags(): void
    {
        $this->assertTrue(Cf7FieldUsage::usesFields('[text name] [national_id code]'));
        $this->assertTrue(Cf7FieldUsage::usesFields("[mobile_ir* phone placeholder \"0912\"]"));
        $this->assertTrue(Cf7FieldUsage::usesFields('[iban_ir]'));
        $this->assertFalse(Cf7FieldUsage::usesFields('[text national_id] [tel mobile_ir]'));
        $this->assertFalse(Cf7FieldUsage::usesFields('[national_identity x]'));
    }

    public function test_it_lists_the_forms_that_use_them_and_caches_the_list(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_posts')->justReturn([7, 8, 9]);
        Functions\when('get_post_meta')->alias(fn (int $id) => match ($id) {
            7 => '[national_id code]',
            8 => '[text name]',
            9 => '[card_ir* card]',
        });
        Functions\when('get_post_field')->alias(fn (string $field, int $id) => "Form $id");
        Functions\expect('set_transient')->once()->with(
            Cf7FieldUsage::TRANSIENT,
            [['id' => 7, 'title' => 'Form 7'], ['id' => 9, 'title' => 'Form 9']],
            WEEK_IN_SECONDS
        );

        $this->assertSame(
            [['id' => 7, 'title' => 'Form 7'], ['id' => 9, 'title' => 'Form 9']],
            (new Cf7FieldUsage())->formsUsingFields()
        );
    }

    public function test_the_cached_list_is_used(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 7, 'title' => 'Form 7']]);
        Functions\expect('get_posts')->never();

        $this->assertSame([['id' => 7, 'title' => 'Form 7']], (new Cf7FieldUsage())->formsUsingFields());
    }

    public function test_saving_trashing_or_deleting_a_form_clears_the_cache(): void
    {
        $usage = new Cf7FieldUsage();
        $usage->register();

        $this->assertNotFalse(has_action('wpcf7_after_save', [$usage, 'clear']));
        $this->assertNotFalse(has_action('clean_post_cache', [$usage, 'clearForPost']));

        Functions\expect('delete_transient')->once()->with(Cf7FieldUsage::TRANSIENT);

        $form = new \WP_Post();
        $form->post_type = 'wpcf7_contact_form';
        $page = new \WP_Post();
        $page->post_type = 'page';

        $usage->clearForPost(1, $page);
        $usage->clearForPost(2, $form);
    }
}
