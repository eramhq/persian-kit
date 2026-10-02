<?php

// Polylang's API, as far as Persian Kit uses it, for static analysis.

/**
 * @return string|false
 */
function pll_current_language(string $field = 'slug') {}

/**
 * @return string|false
 */
function pll_default_language(string $field = 'slug') {}

/**
 * @param array<string, mixed> $args
 * @return list<string>
 */
function pll_languages_list(array $args = []): array {}

/**
 * @return string|false
 */
function pll_get_post_language(int $post_id, string $field = 'slug') {}

/**
 * @return string|false
 */
function pll_get_term_language(int $term_id, string $field = 'slug') {}
