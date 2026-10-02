<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Dependencies\Eram\Abzar\Validation\Details\CardNumberDetails;
use PersianKit\Dependencies\Eram\Abzar\Validation\Details\IbanDetails;
use PersianKit\Dependencies\Eram\Abzar\Validation\Details\NationalIdDetails;
use PersianKit\Dependencies\Eram\Abzar\Validation\Details\PhoneNumberDetails;
use PersianKit\Dependencies\Eram\Abzar\Validation\Details\PostalCodeDetails;
use PersianKit\Dependencies\Eram\Abzar\Validation\ValidationResult;

defined('ABSPATH') || exit;

/**
 * Contact Form 7 form tags for Iranian values, each checked when the form
 * is sent:
 *
 *     [mobile_ir your-mobile]   mobile number, sent as 09xxxxxxxxx
 *     [national_id your-id]     national ID (کد ملی), 10 digits
 *     [postcode_ir your-code]   postcode, 10 digits
 *     [card_ir your-card]       bank card number, 16 digits
 *     [iban_ir your-iban]       IBAN (شبا), sent as IR and 24 digits
 *
 * A * after the type (mobile_ir*) makes the field required. The tags take
 * CF7's usual text field options: id, class, placeholder, size, maxlength,
 * autocomplete, readonly and a default value. The value is sent and mailed
 * in its standard form, whatever digits and separators were typed. The
 * error messages can be changed in each form's Messages tab.
 */
class Cf7IranianFields
{
    /** Form tag => attributes of its input. */
    private const FIELDS = [
        'mobile_ir'   => ['type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => null],
        'national_id' => ['type' => 'text', 'autocomplete' => 'off', 'inputmode' => 'numeric'],
        'postcode_ir' => ['type' => 'text', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric'],
        'card_ir'     => ['type' => 'text', 'autocomplete' => 'cc-number', 'inputmode' => 'numeric'],
        'iban_ir'     => ['type' => 'text', 'autocomplete' => 'off', 'inputmode' => null],
    ];

    public function register(): void
    {
        add_action('wpcf7_init', [$this, 'addFormTags']);
        add_filter('wpcf7_messages', [$this, 'addMessages']);
        add_action('wpcf7_swv_create_schema', [$this, 'addRequiredRules'], 10, 2);

        foreach (array_keys(self::FIELDS) as $type) {
            add_filter("wpcf7_validate_{$type}", [$this, 'validate'], 10, 2);
            add_filter("wpcf7_validate_{$type}*", [$this, 'validate'], 10, 2);
            add_filter("wpcf7_posted_data_{$type}", [$this, 'normalizePostedValue'], 10, 3);
            add_filter("wpcf7_posted_data_{$type}*", [$this, 'normalizePostedValue'], 10, 3);
        }

        // Booted after CF7 ran wpcf7_init on a request that already started.
        if (did_action('wpcf7_init')) {
            $this->addFormTags();
        }
    }

    public function addFormTags(): void
    {
        foreach (array_keys(self::FIELDS) as $type) {
            wpcf7_add_form_tag([$type, "{$type}*"], [$this, 'render'], ['name-attr' => true]);
        }
    }

    /**
     * @param array<string, array{description: string, default: string}> $messages
     * @return array<string, array{description: string, default: string}>
     */
    public function addMessages(array $messages): array
    {
        return array_merge($messages, [
            'invalid_mobile_ir' => [
                'description' => __('Mobile number that the sender entered is invalid', 'persian-kit'),
                'default'     => __('Enter a valid Iranian mobile number, such as 09121234567.', 'persian-kit'),
            ],
            'invalid_national_id' => [
                'description' => __('National ID that the sender entered is invalid', 'persian-kit'),
                'default'     => __('The national ID is not valid. Check the 10 digits on the national card.', 'persian-kit'),
            ],
            'invalid_postcode_ir' => [
                'description' => __('Postcode that the sender entered is invalid', 'persian-kit'),
                'default'     => __('Enter a valid Iranian postcode. Postcodes have 10 digits.', 'persian-kit'),
            ],
            'invalid_card_ir' => [
                'description' => __('Bank card number that the sender entered is invalid', 'persian-kit'),
                'default'     => __('The card number is not valid. Check the 16 digits on the card.', 'persian-kit'),
            ],
            'invalid_iban_ir' => [
                'description' => __('IBAN that the sender entered is invalid', 'persian-kit'),
                'default'     => __('The IBAN (Sheba) is not valid. It has IR and 24 digits.', 'persian-kit'),
            ],
        ]);
    }

    /**
     * CF7 checks a required field in the browser too, from these rules.
     *
     * @param object $schema       WPCF7_SWV_Schema
     * @param object $contactForm  WPCF7_ContactForm
     */
    public function addRequiredRules($schema, $contactForm): void
    {
        if (!function_exists('wpcf7_swv_create_rule') || !method_exists($contactForm, 'scan_form_tags')) {
            return;
        }

        foreach ($contactForm->scan_form_tags(['basetype' => array_keys(self::FIELDS)]) as $tag) {
            if ($tag->is_required()) {
                $schema->add_rule(wpcf7_swv_create_rule('required', [
                    'field' => $tag->name,
                    'error' => wpcf7_get_message('invalid_required'),
                ]));
            }
        }
    }

    /**
     * @param \WPCF7_FormTag $tag
     */
    public function render($tag): string
    {
        if (empty($tag->name) || !isset(self::FIELDS[$tag->basetype])) {
            return '';
        }

        $field = self::FIELDS[$tag->basetype];
        $error = wpcf7_get_validation_error($tag->name);

        $class = wpcf7_form_controls_class($tag->type, 'wpcf7-text');
        if ($error) {
            $class .= ' wpcf7-not-valid';
        }

        $atts = [
            'size'         => $tag->get_size_option('40'),
            'maxlength'    => $tag->get_maxlength_option('40'),
            'class'        => $tag->get_class_option($class),
            'id'           => $tag->get_id_option(),
            'tabindex'     => $tag->get_option('tabindex', 'signed_int', true),
            'readonly'     => $tag->has_option('readonly'),
            'autocomplete' => $tag->get_autocomplete_option() ?: $field['autocomplete'],
            'inputmode'    => $field['inputmode'],
            // Digits and Latin letters read left to right in a Persian form.
            'dir'          => 'ltr',
        ];

        if ($tag->is_required()) {
            $atts['aria-required'] = 'true';
        }

        if ($error) {
            $atts['aria-invalid'] = 'true';
            $atts['aria-describedby'] = wpcf7_get_validation_error_reference($tag->name);
        } else {
            $atts['aria-invalid'] = 'false';
        }

        $value = (string) reset($tag->values);
        if ($tag->has_option('placeholder') || $tag->has_option('watermark')) {
            $atts['placeholder'] = $value;
            $value = '';
        }

        $atts['value'] = wpcf7_get_hangover($tag->name, $tag->get_default_option($value));
        $atts['type'] = $field['type'];
        $atts['name'] = $tag->name;

        return sprintf(
            '<span class="wpcf7-form-control-wrap" data-name="%1$s"><input %2$s />%3$s</span>',
            esc_attr($tag->name),
            wpcf7_format_atts(array_filter($atts, static fn ($value) => $value !== null)),
            $error
        );
    }

    /**
     * @param \WPCF7_Validation $result
     * @param \WPCF7_FormTag    $tag
     * @return \WPCF7_Validation
     */
    public function validate($result, $tag)
    {
        $value = $this->submittedValue((string) $tag->name);

        if ($value === '') {
            if ($tag->is_required()) {
                $result->invalidate($tag, wpcf7_get_message('invalid_required'));
            }

            return $result;
        }

        if (self::standardValue((string) $tag->basetype, $value) === null) {
            $result->invalidate($tag, wpcf7_get_message('invalid_' . $tag->basetype));
        }

        return $result;
    }

    /**
     * The value is sent and mailed in its standard form when it is valid,
     * and with English digits otherwise.
     *
     * @param mixed $value
     * @param mixed $valueOrig
     * @param mixed $tag WPCF7_FormTag
     * @return mixed
     */
    public function normalizePostedValue($value, $valueOrig, $tag)
    {
        if (!is_string($value) || $value === '' || !is_object($tag)) {
            return $value;
        }

        return self::standardValue((string) ($tag->basetype ?? ''), $value) ?? persian_kit_to_english_digits(trim($value));
    }

    /**
     * The value in its standard form, or null when it is not valid.
     */
    public static function standardValue(string $type, string $value): ?string
    {
        $result = match ($type) {
            'mobile_ir'   => persian_kit_validate_phone($value),
            'national_id' => persian_kit_validate_national_id($value),
            'postcode_ir' => persian_kit_validate_postal_code($value),
            'card_ir'     => persian_kit_validate_card_number($value),
            'iban_ir'     => persian_kit_validate_iban($value),
            default       => null,
        };

        if (!$result instanceof ValidationResult || !$result->isValid()) {
            return null;
        }

        $detail = $result->detail();

        return match (true) {
            $detail instanceof PhoneNumberDetails => $detail->isMobile() ? $detail->normalizedLocal : null,
            $detail instanceof NationalIdDetails  => $detail->value,
            $detail instanceof PostalCodeDetails  => $detail->postalCode,
            $detail instanceof CardNumberDetails  => $detail->value,
            $detail instanceof IbanDetails        => $detail->value,
            default                               => null,
        };
    }

    /**
     * The submitted value: the one CF7 already fixed when the submission
     * holds it, as CF7's own checks read $_POST.
     */
    private function submittedValue(string $name): string
    {
        $submission = class_exists('WPCF7_Submission') ? \WPCF7_Submission::get_instance() : null;
        $value = $submission ? $submission->get_posted_data($name) : null;

        if ($value === null) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- CF7 checks the form's nonce and spam rules.
            $value = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
        }

        return is_string($value) ? trim($value) : '';
    }
}
