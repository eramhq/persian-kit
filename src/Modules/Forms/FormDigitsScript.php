<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * form-digits.js swaps Persian digits for English ones as people type into
 * the inputs a form builder's script names, such as forminator-digits.js.
 */
final class FormDigitsScript
{
    public const SCRIPT = 'persian-kit-form-digits';

    /**
     * Enqueues a builder's script, after the shared one.
     *
     * @param string $file In public/js/.
     */
    public static function enqueue(string $handle, string $file): void
    {
        wp_register_script(self::SCRIPT, PERSIAN_KIT_URL . 'public/js/form-digits.js', [], PERSIAN_KIT_VERSION, true);
        wp_enqueue_script($handle, PERSIAN_KIT_URL . 'public/js/' . $file, [self::SCRIPT], PERSIAN_KIT_VERSION, true);
    }
}
