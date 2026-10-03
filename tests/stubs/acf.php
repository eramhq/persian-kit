<?php

// Minimal ACF signatures for static analysis: only what the Forms module and the switch call.

namespace {
    /** @return object|null The field type (acf_field), or null when there is none. */
    function acf_get_field_type(string $name) {}

    /**
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    function acf_get_field_groups(array $filter = []): array { return []; }

    /**
     * @param array<string, mixed>|int|string $parent
     * @return list<array<string, mixed>>|false
     */
    function acf_get_fields($parent) { return []; }

    /**
     * @param array<string, mixed>       $field
     * @param array<int, string>|null    $specific
     * @return array<string, mixed>|false
     */
    function acf_update_field(array $field, ?array $specific = null) { return $field; }

    /**
     * @param array<string, mixed>|int|string $id
     * @return array<string, mixed>|false
     */
    function acf_get_field_group($id = 0) { return false; }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>|false
     */
    function acf_update_field_group(array $group) { return $group; }

    /**
     * @param array<string, mixed>|int|string $id
     * @return array<string, mixed>|false
     */
    function acf_get_field($id = 0) { return false; }
}
