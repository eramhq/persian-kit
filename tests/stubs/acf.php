<?php

// Minimal ACF signatures for static analysis: only what the Forms module calls.

namespace {
    /** @return object|null The field type (acf_field), or null when there is none. */
    function acf_get_field_type(string $name) {}
}
