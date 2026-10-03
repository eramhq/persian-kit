<?php

namespace PersianKit\Service\Import\Iran;

use PersianKit\Service\Import\Sources\Shipping\PwsSource;

defined('ABSPATH') || exit;

/**
 * The shipping plugin's state_city terms: provinces at the top, their
 * cities under them, and districts under the cities. Read with one query,
 * so it works while the taxonomy is not registered.
 */
final class PwsTermMap
{
    /** @var array<int, array{0: string, 1: string, 2: int}> Name, slug and parent by term id. */
    private array $terms;

    /** @var array<int, ?string> */
    private array $codes = [];

    /**
     * @param array<int, array{0: string, 1: string, 2: int}> $terms
     */
    public function __construct(array $terms)
    {
        $this->terms = $terms;
    }

    public static function load(): self
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, tt.parent FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s",
            PwsSource::TAXONOMY
        ), ARRAY_A);

        $terms = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $terms[(int) $row['term_id']] = [(string) $row['name'], rawurldecode((string) $row['slug']), (int) $row['parent']];
        }

        return new self($terms);
    }

    /**
     * The live terms, or those in the snapshot when they are gone.
     *
     * @param array<string, mixed> $snapshot
     */
    public static function loadOrSnapshot(array $snapshot): self
    {
        $map = self::load();
        if (!$map->isEmpty() || !is_array($snapshot['term_map'] ?? null)) {
            return $map;
        }

        $terms = [];
        foreach ($snapshot['term_map'] as $id => $term) {
            if (is_array($term) && count($term) === 3) {
                $terms[(int) $id] = [(string) $term[0], (string) $term[1], (int) $term[2]];
            }
        }

        return new self($terms);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    public function toSnapshot(): array
    {
        return $this->terms;
    }

    public function isEmpty(): bool
    {
        return $this->terms === [];
    }

    public function has(int $id): bool
    {
        return isset($this->terms[$id]);
    }

    public function name(int $id): ?string
    {
        return $this->terms[$id][0] ?? null;
    }

    public function parent(int $id): int
    {
        return $this->terms[$id][2] ?? 0;
    }

    /**
     * 0 for a province, 1 for a city, 2 or more for a district; -1 when the
     * term is gone.
     */
    public function depth(int $id): int
    {
        if (!$this->has($id)) {
            return -1;
        }

        $depth = 0;
        $seen = [$id => true];
        while (($parent = $this->parent($id)) !== 0 && $this->has($parent) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            $id = $parent;
            $depth++;
        }

        return $depth;
    }

    /**
     * The term's province term.
     */
    public function root(int $id): ?int
    {
        if (!$this->has($id)) {
            return null;
        }

        $seen = [];
        while (($parent = $this->parent($id)) !== 0 && $this->has($parent) && !isset($seen[$parent])) {
            $seen[$parent] = true;
            $id = $parent;
        }

        return $id;
    }

    /**
     * The city a city or district term is in.
     */
    public function city(int $id): ?int
    {
        $depth = $this->depth($id);
        if ($depth < 1) {
            return null;
        }

        while ($this->depth($id) > 1) {
            $id = $this->parent($id);
        }

        return $id;
    }

    /**
     * The WooCommerce code of the province a term is in: from its slug (the
     * two-letter code the plugin installs), or its name when renamed.
     */
    public function provinceCode(int $id): ?string
    {
        $root = $this->root($id);
        if ($root === null) {
            return null;
        }

        if (!array_key_exists($root, $this->codes)) {
            [$name, $slug] = $this->terms[$root];
            $this->codes[$root] = IranProvinces::fromLegacy($slug) ?? IranProvinces::fromName($name);
        }

        return $this->codes[$root];
    }
}
