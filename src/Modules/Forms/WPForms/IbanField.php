<?php

namespace PersianKit\Modules\Forms\WPForms;

defined('ABSPATH') || exit;

class IbanField extends IranianField
{
    public $type = 'persian-kit-iban';

    public $icon = 'fa-university';

    public $order = 50;

    public static function iranianType(): string
    {
        return 'iban_ir';
    }
}
