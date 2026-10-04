<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateInputParser;

defined('ABSPATH') || exit;

/**
 * Fixes what people type into Gravity Forms fields before Gravity Forms
 * checks and saves them:
 *
 *     Phone, Number, Quantity, Time,      Persian and Arabic digits become
 *     the address's postcode, a           English
 *     product's quantity and a price
 *     the customer enters
 *     Date fields                         a Jalali date, typed or picked in
 *                                         the boxes and drop-downs, becomes
 *                                         the Gregorian date in the field's
 *                                         format and order
 *     Iranian fields                      saved in their standard form when
 *                                         valid, such as 09121234567
 *
 * Gravity Forms reads the submission from $_POST again and again (to check
 * it, in the Number field's own check, to save it), so the values are fixed
 * there, before it starts: when a form is sent from the page or over AJAX,
 * through GFAPI::submit_form(), saved to continue later, and checked with
 * GFAPI::validate_form(). Running twice changes nothing. Other text keeps
 * its digits, and the inputs Gravity Forms checks against what it printed
 * (choices, hidden fields, product names and prices) are left alone, or it
 * would report them as changed.
 *
 * A Jalali date that doesn't exist, such as 1405/07/31, fails with Gravity
 * Forms' own date message: read as Gregorian, it would pass as the year
 * 1405.
 *
 * gravityforms-digits.js fixes the digits as they are typed, before the
 * input masks drop them.
 */
class GravityFormsInputNormalizer
{
    public const SCRIPT = 'persian-kit-gravityforms-digits';

    /**
     * Field types whose inputs get English digits: each input's suffix after
     * input_<id>. Address and product fields are checked against what was
     * printed; their postcode and quantity are not.
     */
    private const DIGIT_INPUTS = [
        'phone'         => [''],
        'number'        => [''],
        'quantity'      => [''],
        'time'          => [''],
        'date'          => [''],
        'address'       => ['_5'],
        'singleproduct' => ['_3'],
        'calculation'   => ['_3'],
        'price'         => [''],
    ];

    /** @var array<int, array<string, true>> Jalali dates that don't exist, by form and field. */
    private array $invalidDates = [];

    public function register(): void
    {
        add_filter('gform_pre_process', [$this, 'normalizeSubmission']);
        add_filter('gform_pre_validation', [$this, 'normalizeSubmission']);
        add_filter('gform_field_validation', [$this, 'rejectInvalidDate'], 10, 4);
        add_action('gform_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Gravity Forms checks the form's nonce, and sanitizes each value when
     * it saves it.
     *
     * @param mixed $form
     * @return mixed
     */
    public function normalizeSubmission($form)
    {
        if (is_array($form) && !empty($_POST)) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $_POST = $this->normalizePost($_POST, $form); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        return $form;
    }

    /**
     * @param array<mixed>         $post The submitted values.
     * @param array<string, mixed> $form
     * @return array<mixed>
     */
    public function normalizePost(array $post, array $form): array
    {
        $formId = (int) ($form['id'] ?? 0);
        unset($this->invalidDates[$formId]);

        foreach (is_array($form['fields'] ?? null) ? $form['fields'] : [] as $field) {
            $id = is_object($field) ? ($field->id ?? null) : null;
            if (!is_scalar($id)) {
                continue;
            }

            $iranianType = GravityFormsIranianFields::iranianType($field);
            if ($iranianType !== null) {
                if (isset($post["input_{$id}"]) && is_string($post["input_{$id}"])) {
                    $post["input_{$id}"] = IranianFieldTypes::normalize($iranianType, $post["input_{$id}"]);
                }

                continue;
            }

            foreach (self::digitInputs($field) as $suffix) {
                $key = "input_{$id}{$suffix}";
                if (!isset($post[$key])) {
                    continue;
                }

                $post[$key] = GravityFormsDateField::isJalali($field)
                    ? $this->normalizeDate($field, $post[$key], $formId)
                    : self::englishDigits($post[$key]);
            }
        }

        return $post;
    }

    /**
     * A Jalali date that doesn't exist fails, whatever Gravity Forms found.
     *
     * @param mixed $result {is_valid: bool, message: string}
     * @param mixed $value
     * @param mixed $form
     * @param mixed $field
     * @return mixed
     */
    public function rejectInvalidDate($result, $value = null, $form = [], $field = null)
    {
        if (!is_array($result) || empty($result['is_valid']) || !is_array($form) || !is_object($field)
            || empty($this->invalidDates[(int) ($form['id'] ?? 0)][(string) ($field->id ?? '')])
        ) {
            return $result;
        }

        return ['is_valid' => false, 'message' => self::invalidDateMessage($field, $form)];
    }

    /**
     * @param mixed $form
     */
    public function enqueue($form): void
    {
        foreach (is_array($form) && is_array($form['fields'] ?? null) ? $form['fields'] : [] as $field) {
            if (is_object($field) && (self::digitInputs($field) !== [] || GravityFormsIranianFields::iranianType($field) !== null)) {
                FormDigitsScript::enqueue(self::SCRIPT, 'gravityforms-digits.js');

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function digitInputs(object $field): array
    {
        $type = method_exists($field, 'get_input_type') ? $field->get_input_type() : ($field->type ?? '');

        return self::DIGIT_INPUTS[$type] ?? [];
    }

    /**
     * A Date field's value: a Date Picker's text, or the boxes' and
     * drop-downs' parts in the field's order.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeDate(object $field, $value, int $formId)
    {
        $value = self::englishDigits($value);
        $format = GravityFormsDateField::format($field);

        if (is_string($value)) {
            $value = trim($value);
            $date = $value === '' ? null : FormDateValues::toGregorian($value, $format);
            if ($date !== null) {
                return FormDateValues::inFormat($date, $format);
            }

            $this->noteInvalid($formId, $field, preg_match('/(?<!\d)(\d{4})(?!\d)/', $value, $year) ? (int) $year[1] : 0);

            return $value;
        }

        $order = GravityFormsDateField::order($field);
        if (!is_array($value) || count($value) !== 3 || in_array('', array_map(static fn ($part) => is_string($part) ? trim($part) : '', $value), true)) {
            return $value;
        }

        $parts = array_combine($order, array_map(static fn ($part): int => (int) $part, array_values($value)));
        $date = DateInputParser::toGregorian(sprintf('%04d-%d-%d', $parts['Y'], $parts['m'], $parts['d']));
        if ($date === null) {
            $this->noteInvalid($formId, $field, $parts['Y']);

            return $value;
        }

        $gregorian = array_combine(['Y', 'm', 'd'], array_map('intval', explode('-', $date)));

        return array_map(static fn (string $part): string => (string) $gregorian[$part], $order);
    }

    /**
     * A year read as Jalali (DateInputParser) whose date doesn't exist.
     */
    private function noteInvalid(int $formId, object $field, int $year): void
    {
        if ($year >= 1200 && $year <= 1600) {
            $this->invalidDates[$formId][(string) ($field->id ?? '')] = true;
        }
    }

    /**
     * Gravity Forms' own message for a date that isn't one, in its language
     * and with the field's custom message: the field checks a date that
     * fails.
     *
     * @param array<string, mixed> $form
     */
    private static function invalidDateMessage(object $field, array $form): string
    {
        $probe = clone $field;
        $probe->failed_validation = false;
        $probe->validation_message = '';

        if (method_exists($probe, 'validate')) {
            $probe->validate(GravityFormsDateField::style($field) === 'datepicker' ? '00/00/0000' : ['00', '00', '0000'], $form);
        }

        return (string) $probe->validation_message;
    }

    /**
     * @param mixed $value A string, or the parts of a field such as Time.
     * @return mixed
     */
    private static function englishDigits($value)
    {
        if (is_string($value)) {
            return persian_kit_to_english_digits($value);
        }

        return is_array($value) ? array_map(static fn ($part) => is_string($part) ? persian_kit_to_english_digits($part) : $part, $value) : $value;
    }
}
