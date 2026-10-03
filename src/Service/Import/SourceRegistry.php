<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * The plugins a site can switch from. Others can be added with the
 * persian_kit_import_sources filter, as Source objects.
 */
class SourceRegistry
{
    /** @var list<Source> */
    private array $defaults;

    /** @var array<string, Source>|null */
    private ?array $sources = null;

    /**
     * @param list<Source> $defaults
     */
    public function __construct(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * @return array<string, Source>
     */
    public function all(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        // The plugins a site can switch from to Persian Kit, as Source objects.
        $sources = (array) apply_filters('persian_kit_import_sources', $this->defaults);

        $this->sources = [];
        foreach ($sources as $source) {
            if (is_object($source) && $source instanceof Source && preg_match('/^[a-z0-9-]+$/', $source->key()) === 1) {
                $this->sources[$source->key()] = $source;
            }
        }

        return $this->sources;
    }

    public function get(string $key): ?Source
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Forgets the filtered list, as when a test changes it.
     */
    public function reset(): void
    {
        $this->sources = null;
    }
}
