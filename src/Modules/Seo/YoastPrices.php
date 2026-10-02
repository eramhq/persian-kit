<?php

namespace PersianKit\Modules\Seo;

use PersianKit\Modules\WooCommerce\SchemaPrices;

defined('ABSPATH') || exit;

/**
 * Yoast SEO's schema graph, in rials: a product it describes itself, or
 * one Yoast WooCommerce SEO adds. WooCommerce's own product markup is
 * already converted by SchemaPrices.
 */
class YoastPrices
{
    public function register(): void
    {
        // Last, after Yoast and its add-ons have built the graph.
        add_filter('wpseo_schema_graph', [$this, 'filterGraph'], PHP_INT_MAX);
    }

    public function filterGraph(mixed $graph): mixed
    {
        return is_array($graph) ? SchemaPrices::toRial($graph) : $graph;
    }
}
