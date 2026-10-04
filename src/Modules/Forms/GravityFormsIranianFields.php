<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\Forms\GravityForms\CardField;
use PersianKit\Modules\Forms\GravityForms\IbanField;
use PersianKit\Modules\Forms\GravityForms\IranianField;
use PersianKit\Modules\Forms\GravityForms\MobileField;
use PersianKit\Modules\Forms\GravityForms\NationalIdField;
use PersianKit\Modules\Forms\GravityForms\PostcodeField;

defined('ABSPATH') || exit;

/**
 * Gravity Forms field types for Iranian values, in an "Iranian fields"
 * group of the form editor's Add Fields panel:
 *
 *     Mobile number     saved as 09xxxxxxxxx
 *     National ID       (کد ملی), 10 digits
 *     Postcode          10 digits
 *     Bank card number  16 digits
 *     IBAN (Sheba)      saved as IR and 24 digits
 *
 * A value that isn't valid fails when the form is sent, with the field's
 * custom validation message if it has one. The types are prefixed
 * (persian_kit_mobile) so another plugin's can't clash with them.
 *
 * Gravity Forms shows a field whose type isn't registered as an empty
 * field, so the types stay registered while the integration is off, as
 * text inputs without checks, and the editor no longer offers them.
 */
class GravityFormsIranianFields
{
    /** Gravity Forms field type => IranianFieldTypes type. */
    public const TYPES = [
        'persian_kit_mobile'      => 'mobile_ir',
        'persian_kit_national_id' => 'national_id',
        'persian_kit_postcode'    => 'postcode_ir',
        'persian_kit_card'        => 'card_ir',
        'persian_kit_iban'        => 'iban_ir',
    ];

    /** @var list<class-string<IranianField>> */
    private const FIELDS = [MobileField::class, NationalIdField::class, PostcodeField::class, CardField::class, IbanField::class];

    /** Whether the fields check their values: the integration is on. */
    private bool $checks = false;

    public function register(): void
    {
        $this->registerFields(true);
        add_filter('gform_field_groups_form_editor', [$this, 'addGroup']);
    }

    public function registerFallback(): void
    {
        $this->registerFields(false);
    }

    /**
     * The Iranian type of a field, or null for other fields.
     */
    public static function iranianType(object $field): ?string
    {
        return self::TYPES[(string) ($field->type ?? '')] ?? null;
    }

    /**
     * Gravity Forms has loaded before Persian Kit boots; the action is for
     * code that boots it earlier.
     */
    private function registerFields(bool $checks): void
    {
        $this->checks = $checks;

        add_action('gform_loaded', [$this, 'addFields']);
        if (did_action('gform_loaded')) {
            $this->addFields();
        }
    }

    public function addFields(): void
    {
        if (!class_exists('GF_Fields') || !class_exists('GF_Field_Text')) {
            return;
        }

        IranianField::$checks = $this->checks;

        foreach (self::FIELDS as $class) {
            $field = new $class();
            if (!\GF_Fields::exists($field->type)) {
                \GF_Fields::register($field);
            }
        }
    }

    /**
     * The group the fields add their buttons to.
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
            'name'   => IranianField::GROUP,
            'label'  => IranianFieldTypes::groupLabel(),
            'fields' => [],
        ];

        return $groups;
    }
}
