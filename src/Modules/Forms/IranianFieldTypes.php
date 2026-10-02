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
 * The Iranian field types every form builder integration offers, defined
 * once: each one's label, input attributes, error message, and how a typed
 * value is checked and stored.
 *
 *     mobile_ir    mobile number, stored as 09xxxxxxxxx
 *     national_id  national ID (کد ملی), 10 digits
 *     postcode_ir  postcode, 10 digits
 *     card_ir      bank card number, 16 digits
 *     iban_ir      IBAN (شبا), stored as IR and 24 digits
 *
 * Values are stored in their standard form with English digits, whatever
 * digits and separators were typed.
 */
final class IranianFieldTypes
{
    /** Type => attributes of its input. maxlength leaves room for spaces and dashes. */
    private const INPUTS = [
        'mobile_ir'   => ['type' => 'tel', 'autocomplete' => 'tel-national', 'inputmode' => null, 'maxlength' => 20],
        'national_id' => ['type' => 'text', 'autocomplete' => 'off', 'inputmode' => 'numeric', 'maxlength' => 12],
        'postcode_ir' => ['type' => 'text', 'autocomplete' => 'postal-code', 'inputmode' => 'numeric', 'maxlength' => 12],
        'card_ir'     => ['type' => 'text', 'autocomplete' => 'cc-number', 'inputmode' => 'numeric', 'maxlength' => 23],
        'iban_ir'     => ['type' => 'text', 'autocomplete' => 'off', 'inputmode' => null, 'maxlength' => 34],
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::INPUTS);
    }

    public static function exists(string $type): bool
    {
        return isset(self::INPUTS[$type]);
    }

    /**
     * The group name builders show the types under. Form editors may not
     * know what Persian Kit is, so it names the fields, not the plugin.
     */
    public static function groupLabel(): string
    {
        return __('Iranian fields', 'persian-kit');
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'mobile_ir'   => __('Mobile number', 'persian-kit'),
            'national_id' => __('National ID', 'persian-kit'),
            'postcode_ir' => __('Postcode', 'persian-kit'),
            'card_ir'     => __('Bank card number', 'persian-kit'),
            'iban_ir'     => __('IBAN (Sheba)', 'persian-kit'),
            default       => $type,
        };
    }

    /**
     * Attributes for the field's input. Digits and Latin letters read left
     * to right in a Persian form.
     *
     * @return array{type: string, autocomplete: string, inputmode: ?string, maxlength: int, dir: string}
     */
    public static function inputAttributes(string $type): array
    {
        return (self::INPUTS[$type] ?? ['type' => 'text', 'autocomplete' => 'off', 'inputmode' => null, 'maxlength' => 40]) + ['dir' => 'ltr'];
    }

    /**
     * The error shown when the value is not valid.
     */
    public static function message(string $type): string
    {
        return match ($type) {
            'mobile_ir'   => __('Enter a valid Iranian mobile number, such as 09121234567.', 'persian-kit'),
            'national_id' => __('The national ID is not valid. Check the 10 digits on the national card.', 'persian-kit'),
            'postcode_ir' => __('Enter a valid Iranian postcode. Postcodes have 10 digits.', 'persian-kit'),
            'card_ir'     => __('The card number is not valid. Check the 16 digits on the card.', 'persian-kit'),
            'iban_ir'     => __('The IBAN (Sheba) is not valid. It has IR and 24 digits.', 'persian-kit'),
            default       => '',
        };
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
     * The value to store: its standard form when it is valid, and with
     * English digits otherwise.
     */
    public static function normalize(string $type, string $value): string
    {
        return self::standardValue($type, $value) ?? persian_kit_to_english_digits(trim($value));
    }
}
