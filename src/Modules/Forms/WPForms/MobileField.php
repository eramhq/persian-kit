<?php

namespace PersianKit\Modules\Forms\WPForms;

defined('ABSPATH') || exit;

class MobileField extends IranianField
{
    public $type = 'persian-kit-mobile';

    public $icon = 'fa-mobile';

    public $order = 10;

    public static function iranianType(): string
    {
        return 'mobile_ir';
    }
}
