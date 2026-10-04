<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Service\Import\Iran\IranProvinces;

defined('ABSPATH') || exit;

/**
 * An "Iran" address type for Gravity Forms' Address field: Iran as the
 * country, the 31 provinces in Persian for the state, and a postcode that
 * must be a valid Iranian one, saved as 10 English digits
 * (GravityFormsInputNormalizer).
 *
 * The type stays while the integration is off, so forms keep their address
 * type, and Gravity Forms finds the same provinces it printed when it
 * checks the chosen one. Only the postcode check stops.
 */
class GravityFormsAddress
{
    public const TYPE = 'iran';

    public function register(): void
    {
        $this->registerFallback();
        add_filter('gform_field_validation', [$this, 'validatePostcode'], 10, 4);
    }

    public function registerFallback(): void
    {
        add_filter('gform_address_types', [$this, 'addType']);
    }

    /**
     * @param mixed $types
     * @return mixed
     */
    public function addType($types)
    {
        if (!is_array($types) || isset($types[self::TYPE])) {
            return $types;
        }

        $types[self::TYPE] = [
            'label'       => __('Iran', 'persian-kit'),
            'zip_label'   => __('Postcode', 'persian-kit'),
            'state_label' => __('Province', 'persian-kit'),
            'country'     => self::country(),
            'states'      => array_merge([''], self::provinces()),
        ];

        return $types;
    }

    /**
     * An Iran address's postcode, when one was entered.
     *
     * @param mixed $result {is_valid: bool, message: string}
     * @param mixed $value  The address's inputs by id, such as 3.5.
     * @param mixed $form
     * @param mixed $field
     * @return mixed
     */
    public function validatePostcode($result, $value = [], $form = [], $field = null)
    {
        if (!is_array($result) || empty($result['is_valid']) || !is_object($field) || !self::isIran($field) || !is_array($value)) {
            return $result;
        }

        $postcode = $value[($field->id ?? '') . '.5'] ?? '';
        if (!is_string($postcode) || trim($postcode) === '' || IranianFieldTypes::standardValue('postcode_ir', $postcode) !== null) {
            return $result;
        }

        return ['is_valid' => false, 'message' => IranianFieldTypes::message('postcode_ir')];
    }

    public static function isIran(object $field): bool
    {
        $type = method_exists($field, 'get_input_type') ? $field->get_input_type() : ($field->type ?? '');

        return $type === 'address' && ($field->addressType ?? '') === self::TYPE;
    }

    /**
     * The 31 provinces, in Persian alphabetical order.
     *
     * @return list<string>
     */
    public static function provinces(): array
    {
        $names = array_values(IranProvinces::NAMES);
        $collator = class_exists('Collator') ? new \Collator('fa_IR') : null;

        if ($collator === null || !$collator->sort($names)) {
            sort($names);
        }

        return $names;
    }

    /**
     * Gravity Forms 3.0.3 and later read a country code; earlier releases
     * read the country's name, in their own language.
     */
    private static function country(): string
    {
        $address = class_exists('GF_Fields') ? \GF_Fields::get('address') : null;
        if (!is_object($address) || method_exists($address, 'is_country_code') || !method_exists($address, 'get_default_countries')) {
            return 'IR';
        }

        $countries = $address->get_default_countries();

        return is_array($countries) && is_string($countries['IR'] ?? null) ? $countries['IR'] : 'Iran';
    }
}
