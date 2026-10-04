<?php

namespace PersianKit\Modules\Forms\WPForms;

defined('ABSPATH') || exit;

class NationalIdField extends IranianField
{
    public $type = 'persian-kit-national-id';

    public $icon = 'fa-id-card-o';

    public $order = 20;

    public static function iranianType(): string
    {
        return 'national_id';
    }
}
