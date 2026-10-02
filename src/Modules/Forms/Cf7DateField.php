<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateInputParser;
use PersianKit\Modules\DateConversion\DatePicker;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Contact Form 7's [date] fields with the Jalali date picker. The field
 * still submits Y-m-d, which CF7 validates and stores as before. Without
 * JavaScript it is a text field, and a typed Jalali date is converted
 * (Cf7InputNormalizer).
 *
 * [date name gregorian] keeps CF7's own date input, as do date fields on
 * pages not in Persian.
 */
class Cf7DateField
{
    /** Added to the date inputs that get the picker. */
    public const CLASS_NAME = 'persian-kit-jalali-date';

    public function __construct(private bool $jalaliMail)
    {
    }

    public function register(): void
    {
        add_filter('wpcf7_form_tag', [$this, 'markTag']);
        add_filter('wpcf7_form_elements', [$this, 'upgradeInputs']);

        if ($this->jalaliMail) {
            add_filter('wpcf7_mail_tag_replaced_date', [$this, 'formatMailTag'], 10, 4);
            add_filter('wpcf7_mail_tag_replaced_date*', [$this, 'formatMailTag'], 10, 4);
        }
    }

    /**
     * CF7 passes each tag as an array before it builds the form tag, so a
     * class option added here reaches the input.
     *
     * @param array<string, mixed> $tag
     * @return array<string, mixed>
     */
    public function markTag(array $tag): array
    {
        $options = (array) ($tag['options'] ?? []);

        if (($tag['basetype'] ?? '') !== 'date' || in_array('gregorian', $options, true)) {
            return $tag;
        }

        $options[] = 'class:' . self::CLASS_NAME;
        $tag['options'] = $options;

        return $tag;
    }

    /**
     * Marks the form's date inputs for the picker and loads it.
     */
    public function upgradeInputs(string $html): string
    {
        if (!str_contains($html, self::CLASS_NAME) || !ContentLanguage::displaysPersian()) {
            return $html;
        }

        $processor = new \WP_HTML_Tag_Processor($html);
        $found = false;

        while ($processor->next_tag(['tag_name' => 'input', 'class_name' => self::CLASS_NAME])) {
            foreach (DatePicker::attributes() as $name => $value) {
                $processor->set_attribute($name, $value === '' ? true : $value);
            }

            // Without JavaScript: a text field for a typed Jalali date,
            // instead of the browser's Gregorian one.
            $processor->set_attribute('type', 'text');
            $found = true;
        }

        if (!$found) {
            return $html;
        }

        DatePicker::enqueue();

        return $processor->get_updated_html();
    }

    /**
     * [date-field] in an email shows the Jalali date in the site's date
     * format. [_raw_date-field] and a tag with its own format, such as
     * [_format_date-field "Y-m-d"], keep CF7's Gregorian output.
     *
     * @param mixed $replaced
     * @param mixed $submitted
     * @param bool  $html      Whether the email is HTML.
     * @param mixed $mailTag   WPCF7_MailTag
     * @return mixed
     */
    public function formatMailTag($replaced, $submitted, bool $html, $mailTag)
    {
        if (!is_string($submitted) || !is_object($mailTag) || !method_exists($mailTag, 'get_option')
            || !empty($mailTag->get_option('format')) || !empty($mailTag->get_option('do_not_heat'))
            || !$this->sentFromPersianPage()
        ) {
            return $replaced;
        }

        $date = DateInputParser::toGregorian($submitted);
        $jalali = $date === null ? null : JalaliFormatter::fromLocalMysql((string) get_option('date_format'), $date . ' 00:00:00');

        if ($jalali === null) {
            return $replaced;
        }

        return $html ? esc_html($jalali) : $jalali;
    }

    /**
     * Forms are sent through REST, which has no page language of its own,
     * so the page the form was on decides.
     */
    private function sentFromPersianPage(): bool
    {
        $submission = class_exists('WPCF7_Submission') ? \WPCF7_Submission::get_instance() : null;
        $pageId = $submission === null ? 0 : (int) $submission->get_meta('container_post_id');

        return $pageId > 0 ? ContentLanguage::postIsPersian($pageId) : ContentLanguage::displaysPersian();
    }
}
