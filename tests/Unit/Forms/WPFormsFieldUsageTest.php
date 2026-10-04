<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\WPFormsFieldUsage;
use PHPUnit\Framework\TestCase;

class WPFormsFieldUsageTest extends TestCase
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

    public function test_it_finds_iranian_and_jalali_date_fields(): void
    {
        $this->assertTrue(WPFormsFieldUsage::usesFields(['fields' => [['type' => 'name'], ['type' => 'persian-kit-national-id']]]));
        $this->assertTrue(WPFormsFieldUsage::usesFields(['fields' => ['3' => ['type' => 'persian-kit-date']]]));

        $this->assertFalse(WPFormsFieldUsage::usesFields(['fields' => [['type' => 'text', 'css' => 'persian-kit-mobile']]]));
        $this->assertFalse(WPFormsFieldUsage::usesFields(['fields' => 'broken']));
        $this->assertFalse(WPFormsFieldUsage::usesFields(null));
    }

    public function test_it_lists_the_forms_that_use_them_and_caches_the_list(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_posts')->justReturn([12, 13, 14]);
        Functions\when('get_post_field')->alias(fn (string $field, int $id) => $field === 'post_title' ? "Form $id" : match ($id) {
            12 => json_encode(['id' => 12, 'fields' => ['1' => ['id' => 1, 'type' => 'persian-kit-postcode']]]),
            13 => json_encode(['id' => 13, 'fields' => ['1' => ['id' => 1, 'type' => 'text']]]),
            14 => json_encode(['id' => 14, 'fields' => ['2' => ['id' => 2, 'type' => 'persian-kit-date']]]),
        });
        Functions\expect('set_transient')->once()->with(
            WPFormsFieldUsage::TRANSIENT,
            [['id' => 12, 'title' => 'Form 12'], ['id' => 14, 'title' => 'Form 14']],
            WEEK_IN_SECONDS
        );

        $this->assertSame(
            [['id' => 12, 'title' => 'Form 12'], ['id' => 14, 'title' => 'Form 14']],
            (new WPFormsFieldUsage())->formsUsingFields()
        );
    }

    public function test_the_cached_list_is_used(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 12, 'title' => 'Form 12']]);
        Functions\expect('get_posts')->never();

        $this->assertSame([['id' => 12, 'title' => 'Form 12']], (new WPFormsFieldUsage())->formsUsingFields());
    }

    public function test_a_change_to_a_form_clears_the_cache(): void
    {
        $usage = new WPFormsFieldUsage();
        $usage->register();
        $this->assertNotFalse(has_action('clean_post_cache', [$usage, 'clearForPost']));

        Functions\expect('delete_transient')->once()->with(WPFormsFieldUsage::TRANSIENT);

        $form = new \WP_Post();
        $form->post_type = 'wpforms';
        $page = new \WP_Post();
        $page->post_type = 'page';

        $usage->clearForPost(1, $page);
        $usage->clearForPost(2, $form);
    }
}
