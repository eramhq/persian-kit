<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\Forms\WPForms\CardField;
use PersianKit\Modules\Forms\WPForms\IbanField;
use PersianKit\Modules\Forms\WPForms\IranianField;
use PersianKit\Modules\Forms\WPForms\MobileField;
use PersianKit\Modules\Forms\WPForms\NationalIdField;
use PersianKit\Modules\Forms\WPForms\PostcodeField;

defined('ABSPATH') || exit;

/**
 * WPForms field types for Iranian values, in an "Iranian fields" group of
 * the builder's Add Fields panel. They work in WPForms Lite:
 *
 *     Mobile number     saved as 09xxxxxxxxx
 *     National ID       (کد ملی), 10 digits
 *     Postcode          10 digits
 *     Bank card number  16 digits
 *     IBAN (Sheba)      saved as IR and 24 digits
 *
 * A value that isn't valid fails when the form is sent. The types are
 * prefixed (persian-kit-mobile) so another plugin's can't clash with them.
 *
 * WPForms leaves out a field whose type isn't loaded, so the types stay
 * loaded while the integration is off, as text inputs without checks, and
 * the builder no longer offers them.
 */
class WPFormsIranianFields
{
    /** WPForms field type => IranianFieldTypes type. */
    public const TYPES = [
        'persian-kit-mobile'      => 'mobile_ir',
        'persian-kit-national-id' => 'national_id',
        'persian-kit-postcode'    => 'postcode_ir',
        'persian-kit-card'        => 'card_ir',
        'persian-kit-iban'        => 'iban_ir',
    ];

    /** @var array<string, class-string<IranianField>> */
    private const FIELDS = [
        'persian-kit-mobile'      => MobileField::class,
        'persian-kit-national-id' => NationalIdField::class,
        'persian-kit-postcode'    => PostcodeField::class,
        'persian-kit-card'        => CardField::class,
        'persian-kit-iban'        => IbanField::class,
    ];

    /** Whether the fields check their values: the integration is on. */
    private bool $checks = false;

    public function register(): void
    {
        $this->registerFields(true);
        add_filter('wpforms_builder_fields_buttons', [$this, 'addGroup']);
    }

    public function registerFallback(): void
    {
        $this->registerFields(false);
    }

    /**
     * The Iranian type of a field's settings, or null for other fields.
     *
     * @param array<string, mixed> $field
     */
    public static function iranianType(array $field): ?string
    {
        return self::TYPES[(string) ($field['type'] ?? '')] ?? null;
    }

    /**
     * WPForms loads its own fields on init. A field's name is translated
     * when it is created, so the fields wait for init too.
     */
    private function registerFields(bool $checks): void
    {
        $this->checks = $checks;

        add_action('init', [$this, 'addFields']);
        if (did_action('init')) {
            $this->addFields();
        }
    }

    public function addFields(): void
    {
        if (!class_exists('WPForms_Field')) {
            return;
        }

        IranianField::$checks = $this->checks;

        // A field hooks itself into WPForms when it is created, once.
        foreach (self::FIELDS as $type => $class) {
            if (!has_filter("wpforms_fields_get_field_object_{$type}")) {
                new $class();
            }
        }
    }

    /**
     * The group the fields add their buttons to, after WPForms' own. The
     * fields add theirs later (priority 15).
     *
     * @param mixed $groups
     * @return mixed
     */
    public function addGroup($groups)
    {
        if (!is_array($groups)) {
            return $groups;
        }

        $groups[IranianField::GROUP] = [
            'group_name' => IranianFieldTypes::groupLabel(),
            'fields'     => [],
        ];

        return $groups;
    }
}
