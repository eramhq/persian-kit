<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * What kind of request this is, decided from the request itself: WooCommerce
 * can cache lists for the request before REST_REQUEST is defined. The
 * request doesn't change, so each answer is worked out once.
 */
class StorefrontRequest
{
    private ?bool $storefront = null;

    private ?bool $storeApi = null;

    /**
     * Not the admin, cron or WP-CLI, and a REST request only for the Store API.
     */
    public function isStorefront(): bool
    {
        if ($this->storefront !== null) {
            return $this->storefront;
        }

        if (is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return $this->storefront = false;
        }

        $route = $this->restRoute();

        return $this->storefront = $route === null || $this->isStoreApiRoute($route);
    }

    /**
     * A request to the Store API, which the cart and checkout blocks use.
     */
    public function isStoreApi(): bool
    {
        if ($this->storeApi !== null) {
            return $this->storeApi;
        }

        $route = $this->restRoute();

        return $this->storeApi = $route !== null && $this->isStoreApiRoute($route);
    }

    private function isStoreApiRoute(string $route): bool
    {
        return $route === 'wc/store' || str_starts_with($route, 'wc/store/');
    }

    /**
     * The REST route this request asks for, without slashes at the ends, or
     * null when it isn't a REST request.
     */
    private function restRoute(): ?string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared, never stored or printed.
        if (isset($_GET['rest_route']) && is_string($_GET['rest_route'])) {
            return trim(wp_unslash($_GET['rest_route']), '/');
        }

        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        // phpcs:enable

        $path = (string) wp_parse_url($uri, PHP_URL_PATH);
        $prefix = '/' . trim(rest_get_url_prefix(), '/') . '/';
        $position = strpos($path . '/', $prefix);

        if ($position === false) {
            return defined('REST_REQUEST') && REST_REQUEST ? '' : null;
        }

        return trim(substr($path . '/', $position + strlen($prefix)), '/');
    }
}
