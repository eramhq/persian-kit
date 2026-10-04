<?php

namespace PersianKit\Modules\Forms\WPForms;

defined('ABSPATH') || exit;

class PostcodeField extends IranianField
{
    public $type = 'persian-kit-postcode';

    public $icon = 'fa-map-marker';

    public $order = 30;

    public static function iranianType(): string
    {
        return 'postcode_ir';
    }
}
