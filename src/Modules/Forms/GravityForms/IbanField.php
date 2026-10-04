<?php

namespace PersianKit\Modules\Forms\GravityForms;

defined('ABSPATH') || exit;

class IbanField extends IranianField
{
    public $type = 'persian_kit_iban';

    public static function iranianType(): string
    {
        return 'iban_ir';
    }

    public function get_form_editor_field_description(): string
    {
        return esc_attr__('An Iranian IBAN (Sheba), checked and saved as IR and 24 digits.', 'persian-kit');
    }

    public function get_form_editor_field_icon(): string
    {
        return 'gform-icon--numbers-alt';
    }
}
