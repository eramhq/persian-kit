<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\Forms\WPForms\JalaliDateField;

defined('ABSPATH') || exit;

/**
 * Loads the Jalali date field (WPForms\JalaliDateField) in WPForms, in the
 * builder's "Iranian fields" group. While the integration is off it stays
 * loaded as a plain text input, so the forms that use it keep showing it.
 */
class WPFormsDateField
{
    /** Whether the field has its picker and checks: the integration is on. */
    private bool $enabled = false;

    public function register(): void
    {
        $this->registerField(true);
    }

    public function registerFallback(): void
    {
        $this->registerField(false);
    }

    /**
     * After WPForms' own fields, on init, as WPFormsIranianFields does.
     */
    private function registerField(bool $enabled): void
    {
        $this->enabled = $enabled;

        add_action('init', [$this, 'addField']);
        if (did_action('init')) {
            $this->addField();
        }
    }

    public function addField(): void
    {
        if (!class_exists('WPForms_Field')) {
            return;
        }

        JalaliDateField::$enabled = $this->enabled;

        // The field hooks itself into WPForms when it is created, once.
        if (!has_filter('wpforms_fields_get_field_object_persian-kit-date')) {
            new JalaliDateField();
        }
    }
}
