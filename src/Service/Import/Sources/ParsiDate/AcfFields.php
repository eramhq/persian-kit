<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

defined('ABSPATH') || exit;

/**
 * Parsi Date's ACF date fields, and date pickers, in every field group,
 * sub-fields included, whether the group is saved in the database, in
 * local JSON or in PHP.
 */
final class AcfFields
{
    public const JALALI = 'jalali_datepicker';

    public static function acfActive(): bool
    {
        return function_exists('acf_get_field_groups') && function_exists('acf_get_fields');
    }

    /**
     * @return list<array{field: array<string, mixed>, group: array<string, mixed>}>
     */
    public static function ofType(string $type): array
    {
        if (!self::acfActive()) {
            return [];
        }

        $found = [];
        foreach (acf_get_field_groups() as $group) {
            $found = array_merge($found, self::collect((array) acf_get_fields($group), $group, $type));
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    public static function keysOfType(string $type): array
    {
        return array_values(array_unique(array_map(static fn (array $entry): string => (string) $entry['field']['key'], self::ofType($type))));
    }

    /**
     * Whether the field is saved in the database, where it can be changed.
     *
     * @param array<string, mixed> $field
     */
    public static function inDatabase(array $field): bool
    {
        return !empty($field['ID']) && get_post_type((int) $field['ID']) === 'acf-field';
    }

    /**
     * @param array<mixed>         $fields
     * @param array<string, mixed> $group
     * @return list<array{field: array<string, mixed>, group: array<string, mixed>}>
     */
    private static function collect(array $fields, array $group, string $type): array
    {
        $found = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (($field['type'] ?? '') === $type) {
                $found[] = ['field' => $field, 'group' => $group];
            }
            if (is_array($field['sub_fields'] ?? null)) {
                $found = array_merge($found, self::collect($field['sub_fields'], $group, $type));
            }
            // Flexible content keeps its fields under layouts.
            foreach (is_array($field['layouts'] ?? null) ? $field['layouts'] : [] as $layout) {
                if (is_array($layout) && is_array($layout['sub_fields'] ?? null)) {
                    $found = array_merge($found, self::collect($layout['sub_fields'], $group, $type));
                }
            }
        }

        return $found;
    }
}
