<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\Cf7DateField;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Forms\Support\FakeMailTag;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class Cf7DateFieldTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('get_option')->alias(fn (string $option) => $option === 'date_format' ? 'Y/m/d' : false);
        Functions\when('esc_html')->alias(fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES));
    }

    protected function tearDown(): void
    {
        \WPCF7_Submission::$current = null;
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_marks_date_tags_and_formats_their_mail_tags(): void
    {
        $field = new Cf7DateField(true);
        $field->register();

        $this->assertNotFalse(has_filter('wpcf7_form_tag', [$field, 'markTag']));
        $this->assertNotFalse(has_filter('wpcf7_form_elements', [$field, 'upgradeInputs']));
        $this->assertNotFalse(has_filter('wpcf7_mail_tag_replaced_date', [$field, 'formatMailTag']));
        $this->assertNotFalse(has_filter('wpcf7_mail_tag_replaced_date*', [$field, 'formatMailTag']));
    }

    public function test_mail_tags_stay_gregorian_without_jalali_dates(): void
    {
        $field = new Cf7DateField(false);
        $field->register();

        $this->assertNotFalse(has_filter('wpcf7_form_elements', [$field, 'upgradeInputs']));
        $this->assertFalse(has_filter('wpcf7_mail_tag_replaced_date', [$field, 'formatMailTag']));
    }

    public function test_date_tags_get_the_picker_class(): void
    {
        $field = new Cf7DateField(true);

        $tag = $field->markTag(['type' => 'date*', 'basetype' => 'date', 'options' => ['min:today']]);
        $this->assertSame(['min:today', 'class:persian-kit-jalali-date'], $tag['options']);

        $this->assertSame(['class:persian-kit-jalali-date'], $field->markTag(['type' => 'date', 'basetype' => 'date'])['options']);
    }

    public function test_gregorian_date_tags_and_other_tags_are_left_alone(): void
    {
        $field = new Cf7DateField(true);
        $gregorian = ['type' => 'date', 'basetype' => 'date', 'options' => ['gregorian']];
        $text = ['type' => 'text', 'basetype' => 'text', 'options' => []];

        $this->assertSame($gregorian, $field->markTag($gregorian));
        $this->assertSame($text, $field->markTag($text));
    }

    public function test_forms_without_date_fields_are_not_parsed(): void
    {
        $html = '<input type="text" name="name">';

        $this->assertSame($html, (new Cf7DateField(true))->upgradeInputs($html));
    }

    public function test_mail_shows_the_jalali_date(): void
    {
        $field = new Cf7DateField(true);

        $this->assertSame('1405/07/10', $field->formatMailTag('2026-10-02', '2026-10-02', false, new FakeMailTag()));
    }

    public function test_raw_mail_tags_those_with_their_own_format_and_other_values_are_kept(): void
    {
        $field = new Cf7DateField(true);

        $this->assertSame('October 2, 2026', $field->formatMailTag('October 2, 2026', '2026-10-02', false, new FakeMailTag(['format' => 'F j, Y'])));
        $this->assertSame('2026-10-02', $field->formatMailTag('2026-10-02', '2026-10-02', false, new FakeMailTag(['do_not_heat' => true, 'format' => ''])));
        $this->assertSame('', $field->formatMailTag('', '', false, new FakeMailTag()));
        $this->assertSame('soon', $field->formatMailTag('soon', 'soon', true, new FakeMailTag()));
        $this->assertSame(['2026-10-02'], $field->formatMailTag(['2026-10-02'], ['2026-10-02'], false, new FakeMailTag()));
    }

    public function test_pages_not_in_persian_keep_cf7s_date_input(): void
    {
        $this->inLanguage('en_US');
        $html = '<input type="date" name="when" class="wpcf7-date persian-kit-jalali-date">';

        $this->assertSame($html, (new Cf7DateField(true))->upgradeInputs($html));
    }

    public function test_mail_follows_the_language_of_the_page_the_form_was_on(): void
    {
        $source = $this->inLanguage('fa_IR');
        $source->posts = [11 => 'en_US', 12 => 'fa_IR'];
        $field = new Cf7DateField(true);

        \WPCF7_Submission::$current = new \WPCF7_Submission(['container_post_id' => 11]);
        $this->assertSame('2026-10-02', $field->formatMailTag('2026-10-02', '2026-10-02', false, new FakeMailTag()));

        \WPCF7_Submission::$current = new \WPCF7_Submission(['container_post_id' => 12]);
        $this->assertSame('1405/07/10', $field->formatMailTag('2026-10-02', '2026-10-02', false, new FakeMailTag()));
    }

    public function test_mail_from_a_form_on_no_page_follows_the_current_language(): void
    {
        $this->inLanguage('en_US');
        \WPCF7_Submission::$current = new \WPCF7_Submission(['container_post_id' => 0]);

        $this->assertSame('2026-10-02', (new Cf7DateField(true))->formatMailTag('2026-10-02', '2026-10-02', false, new FakeMailTag()));
    }
}
