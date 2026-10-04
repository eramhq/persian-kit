<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Finds the Gravity Forms forms with Iranian fields, for the warning on the
 * settings page when the integration is turned off. The list is cached and
 * cleared whenever a form is saved, duplicated, imported, trashed, restored
 * or deleted.
 */
class GravityFormsFieldUsage
{
    public const TRANSIENT = 'persian_kit_gravityforms_field_usage';

    public function register(): void
    {
        foreach ([
            'gform_after_save_form',
            'gform_post_form_trashed',
            'gform_post_form_restored',
            'gform_after_delete_form',
            'gform_post_form_duplicated',
            'gform_forms_post_import',
        ] as $hook) {
            add_action($hook, [$this, 'clear']);
        }
    }

    /**
     * Forms not in the trash, active or not, by title.
     *
     * @return list<array{id: int, title: string}>
     */
    public function formsUsingFields(): array
    {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            /** @var list<array{id: int, title: string}> $cached */
            return $cached;
        }

        $forms = [];
        foreach (class_exists('GFAPI') ? (array) \GFAPI::get_forms(null, false, 'title') : [] as $form) {
            if (self::usesFields($form)) {
                $forms[] = ['id' => (int) ($form['id'] ?? 0), 'title' => (string) ($form['title'] ?? '')];
            }
        }

        set_transient(self::TRANSIENT, $forms, WEEK_IN_SECONDS);

        return $forms;
    }

    /**
     * @param array<string, mixed> $form
     */
    public static function usesFields(array $form): bool
    {
        foreach (is_array($form['fields'] ?? null) ? $form['fields'] : [] as $field) {
            $field = is_array($field) ? (object) $field : $field;
            if (is_object($field) && GravityFormsIranianFields::iranianType($field) !== null) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        delete_transient(self::TRANSIENT);
    }
}
