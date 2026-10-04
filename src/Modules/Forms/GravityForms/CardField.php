<?php

namespace PersianKit\Modules\Forms\GravityForms;

defined('ABSPATH') || exit;

class CardField extends IranianField
{
    public $type = 'persian_kit_card';

    public static function iranianType(): string
    {
        return 'card_ir';
    }

    public function get_form_editor_field_description(): string
    {
        return esc_attr__('A 16-digit Iranian bank card number, checked against its check digit.', 'persian-kit');
    }

    public function get_form_editor_field_icon(): string
    {
        return 'gform-icon--credit-card';
    }
}
