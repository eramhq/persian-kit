<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\Forms\Cf7FieldUsage;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The list of forms that use the Iranian fields, shown in the warning when
 * the Contact Form 7 integration is turned off. Runs when Contact Form 7 is
 * loaded.
 */
class ContactForm7FieldUsageTest extends WordPressIntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WPCF7_ContactForm')) {
            $this->markTestSkipped('Contact Form 7 is not installed next to the plugin.');
        }

        delete_transient(Cf7FieldUsage::TRANSIENT);
    }

    public function test_it_lists_forms_with_the_fields_and_follows_edits(): void
    {
        $usage = new Cf7FieldUsage();
        $usage->register();

        $order = $this->makeForm('Order', '[national_id* code] [submit]');
        $this->makeForm('Newsletter', '[email your-email] [submit]');

        $this->assertSame([['id' => $order->id(), 'title' => 'Order']], $usage->formsUsingFields());
        $this->assertIsArray(get_transient(Cf7FieldUsage::TRANSIENT));

        // Saving a form clears the list, so the next read sees the edit.
        $signup = $this->makeForm('Signup', '[mobile_ir phone] [submit]');
        $this->assertSame(['Order', 'Signup'], array_column($usage->formsUsingFields(), 'title'));

        wp_trash_post($signup->id());
        $this->assertSame(['Order'], array_column($usage->formsUsingFields(), 'title'));

        $order->set_properties(['form' => '[text name] [submit]']);
        $order->save();
        $this->assertSame([], $usage->formsUsingFields());
    }

    private function makeForm(string $title, string $template): \WPCF7_ContactForm
    {
        $form = \WPCF7_ContactForm::get_template(['title' => $title]);
        $form->set_properties(['form' => $template]);
        $form->save();

        return $form;
    }
}
