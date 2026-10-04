<?php

namespace PersianKit\Modules\Forms\GravityForms;

defined('ABSPATH') || exit;

class PostcodeField extends IranianField
{
    public $type = 'persian_kit_postcode';

    public static function iranianType(): string
    {
        return 'postcode_ir';
    }

    public function get_form_editor_field_description(): string
    {
        return esc_attr__('An Iranian 10-digit postcode.', 'persian-kit');
    }

    public function get_form_editor_field_icon(): string
    {
        return 'gform-icon--place';
    }
}
