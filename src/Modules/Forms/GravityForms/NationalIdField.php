<?php

namespace PersianKit\Modules\Forms\GravityForms;

defined('ABSPATH') || exit;

class NationalIdField extends IranianField
{
    public $type = 'persian_kit_national_id';

    public static function iranianType(): string
    {
        return 'national_id';
    }

    public function get_form_editor_field_description(): string
    {
        return esc_attr__('An Iranian national ID (code melli), checked against its check digit.', 'persian-kit');
    }

    public function get_form_editor_field_icon(): string
    {
        return 'gform-icon--name-2';
    }
}
