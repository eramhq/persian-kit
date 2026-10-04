<?php

namespace PersianKit\Modules\Forms\GravityForms;

defined('ABSPATH') || exit;

class MobileField extends IranianField
{
    public $type = 'persian_kit_mobile';

    public static function iranianType(): string
    {
        return 'mobile_ir';
    }

    public function get_form_editor_field_description(): string
    {
        return esc_attr__('An Iranian mobile number, checked and saved as 09xxxxxxxxx.', 'persian-kit');
    }

    public function get_form_editor_field_icon(): string
    {
        return 'gform-icon--phone';
    }
}
