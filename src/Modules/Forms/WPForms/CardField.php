<?php

namespace PersianKit\Modules\Forms\WPForms;

defined('ABSPATH') || exit;

class CardField extends IranianField
{
    public $type = 'persian-kit-card';

    public $icon = 'fa-credit-card';

    public $order = 40;

    public static function iranianType(): string
    {
        return 'card_ir';
    }
}
