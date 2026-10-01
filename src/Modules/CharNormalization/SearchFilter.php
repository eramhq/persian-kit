<?php

namespace PersianKit\Modules\CharNormalization;

use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;

defined('ABSPATH') || exit;

/**
 * Makes search match both spellings of a word: Persian ی/ک and Arabic ي/ك.
 * Posts saved before normalization (or with it off) keep the Arabic letters,
 * so each search term is matched in its Persian form, its Arabic form and as
 * typed. Digits are left as typed: the database collation decides whether
 * Persian and Latin digits match.
 */
class SearchFilter
{
    private const DEFAULT_COLUMNS = ['post_title', 'post_excerpt', 'post_content'];

    private CharNormalizer $normalizer;

    public function __construct(CharNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function register(): void
    {
        add_filter('posts_search', [$this, 'filterSearch'], 10, 2);
    }

    /**
     * Rebuilds the search clause the way WP_Query::parse_search() does, with
     * one LIKE per spelling of each term.
     */
    public function filterSearch(string $search, \WP_Query $query): string
    {
        // Media library searches also match file names through a join this
        // filter cannot see, so they keep core's clause.
        if ($search === '' || !$query->is_search() || !$query->is_main_query() || str_contains($search, 'sq1.meta_value')) {
            return $search;
        }

        $terms = $query->get('search_terms');
        if (!is_array($terms) || $terms === []) {
            return $search;
        }

        // Same core filter WP_Query::parse_search() applies.
        $exclusionPrefix = apply_filters('wp_query_search_exclusion_prefix', '-'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own hook.
        $parsedTerms = [];
        $hasVariants = false;

        foreach ($terms as $term) {
            if (!is_string($term)) {
                return $search;
            }

            $negated = $exclusionPrefix && str_starts_with($term, $exclusionPrefix);
            if ($negated) {
                $term = substr($term, strlen($exclusionPrefix));
            }

            $variants = $this->variants($term);
            $hasVariants = $hasVariants || count($variants) > 1;
            $parsedTerms[] = ['negated' => $negated, 'variants' => $variants];
        }

        if (!$hasVariants) {
            return $search;
        }

        return $this->buildSearch($query, $parsedTerms);
    }

    /**
     * The term as typed, in Persian letters, and in Arabic letters.
     *
     * @return list<string>
     */
    public function variants(string $term): array
    {
        $persian = $this->normalizer->normalize($term);
        $arabic = str_replace(["\u{06CC}", "\u{06A9}"], ["\u{064A}", "\u{0643}"], $persian);

        return array_values(array_unique([$term, $persian, $arabic]));
    }

    /**
     * @param list<array{negated: bool, variants: list<string>}> $parsedTerms
     */
    private function buildSearch(\WP_Query $query, array $parsedTerms): string
    {
        global $wpdb;

        $wildcard = $query->get('exact') ? '' : '%';
        $columns = $this->searchColumns($query);
        $clauses = [];

        foreach ($parsedTerms as $parsedTerm) {
            $likeOp = $parsedTerm['negated'] ? 'NOT LIKE' : 'LIKE';
            $andOr = $parsedTerm['negated'] ? ' AND ' : ' OR ';
            $parts = [];

            foreach ($columns as $column) {
                foreach ($parsedTerm['variants'] as $variant) {
                    $parts[] = $wpdb->prepare(
                        // $column is one of DEFAULT_COLUMNS; $likeOp is a literal above.
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        "({$wpdb->posts}.{$column} {$likeOp} %s)",
                        $wildcard . $wpdb->esc_like($variant) . $wildcard
                    );
                }
            }

            $clauses[] = '(' . implode($andOr, $parts) . ')';
        }

        $search = ' AND (' . implode(' AND ', $clauses) . ') ';

        if (!is_user_logged_in()) {
            $search .= " AND ({$wpdb->posts}.post_password = '') ";
        }

        return $search;
    }

    /**
     * @return list<string>
     */
    private function searchColumns(\WP_Query $query): array
    {
        $columns = $query->get('search_columns');
        if (empty($columns)) {
            $columns = self::DEFAULT_COLUMNS;
        }

        // Same core filter WP_Query::parse_search() applies.
        $columns = (array) apply_filters('post_search_columns', (array) $columns, (string) $query->get('s'), $query); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own hook.
        $columns = array_values(array_intersect($columns, self::DEFAULT_COLUMNS));

        return $columns === [] ? self::DEFAULT_COLUMNS : $columns;
    }
}
