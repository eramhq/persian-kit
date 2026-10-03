<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\ForminatorFieldUsage;
use PHPUnit\Framework\TestCase;

class ForminatorFieldUsageTest extends TestCase
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

    public function test_it_finds_fields_with_the_iranian_classes(): void
    {
        $this->assertTrue(ForminatorFieldUsage::usesFields(['fields' => [
            ['type' => 'name', 'custom-class' => ''],
            ['type' => 'text', 'custom-class' => 'wide persian-kit-national-id'],
        ]]));
        $this->assertTrue(ForminatorFieldUsage::usesFields(['fields' => [['type' => 'phone', 'custom-class' => 'persian-kit-mobile']]]));

        $this->assertFalse(ForminatorFieldUsage::usesFields(['fields' => [['type' => 'date', 'custom-class' => 'persian-kit-gregorian']]]));
        $this->assertFalse(ForminatorFieldUsage::usesFields(['fields' => [['type' => 'select', 'custom-class' => 'persian-kit-mobile']]]));
        $this->assertFalse(ForminatorFieldUsage::usesFields(['fields' => 'broken']));
        $this->assertFalse(ForminatorFieldUsage::usesFields(''));
    }

    public function test_it_lists_the_forms_that_use_them_and_caches_the_list(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_posts')->justReturn([12, 13, 14]);
        Functions\when('get_post_meta')->alias(fn (int $id) => match ($id) {
            12 => ['fields' => [['type' => 'text', 'custom-class' => 'persian-kit-postcode']], 'settings' => ['formName' => 'Order form']],
            13 => ['fields' => [['type' => 'text']]],
            14 => ['fields' => [['type' => 'number', 'custom-class' => 'persian-kit-card']]],
        });
        Functions\when('get_post_field')->alias(fn (string $field, int $id) => "Form $id");
        Functions\expect('set_transient')->once()->with(
            ForminatorFieldUsage::TRANSIENT,
            [['id' => 12, 'title' => 'Order form'], ['id' => 14, 'title' => 'Form 14']],
            WEEK_IN_SECONDS
        );

        // The builder's name, else the post title (a slug of it).
        $this->assertSame(
            [['id' => 12, 'title' => 'Order form'], ['id' => 14, 'title' => 'Form 14']],
            (new ForminatorFieldUsage())->formsUsingFields()
        );
    }

    public function test_the_cached_list_is_used(): void
    {
        Functions\when('get_transient')->justReturn([['id' => 12, 'title' => 'Form 12']]);
        Functions\expect('get_posts')->never();

        $this->assertSame([['id' => 12, 'title' => 'Form 12']], (new ForminatorFieldUsage())->formsUsingFields());
    }

    public function test_saving_cloning_importing_trashing_or_deleting_a_form_clears_the_cache(): void
    {
        $usage = new ForminatorFieldUsage();
        $usage->register();

        foreach ([
            'forminator_custom_form_action_create',
            'forminator_custom_form_action_update',
            'forminator_form_action_delete',
            'forminator_form_action_clone',
            'forminator_form_action_imported',
        ] as $hook) {
            $this->assertNotFalse(has_action($hook, [$usage, 'clear']), $hook);
        }
        $this->assertNotFalse(has_action('clean_post_cache', [$usage, 'clearForPost']));

        Functions\expect('delete_transient')->once()->with(ForminatorFieldUsage::TRANSIENT);

        $form = new \WP_Post();
        $form->post_type = 'forminator_forms';
        $poll = new \WP_Post();
        $poll->post_type = 'forminator_polls';

        $usage->clearForPost(1, $poll);
        $usage->clearForPost(2, $form);
    }
}
