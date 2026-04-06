<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

class WooAdminDateFields
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hookSuffix = ''): void
    {
        if (!$this->shouldEnqueue($hookSuffix)) {
            return;
        }

        $this->registerSharedScripts();

        wp_enqueue_script('persian-kit-jalali');

        wp_enqueue_script(
            'persian-kit-woocommerce-date-fields',
            PERSIAN_KIT_URL . 'public/js/woocommerce-date-fields.js',
            ['jquery', 'persian-kit-jalali'],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    private function registerSharedScripts(): void
    {
        wp_register_script(
            'persian-kit-jalali',
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    private function shouldEnqueue(string $hookSuffix): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        if ($this->isProductEditScreen($screen, $hookSuffix)) {
            return true;
        }

        if ($this->isCouponEditScreen($screen, $hookSuffix)) {
            return true;
        }

        return $this->isOrderEditScreen($screen, $hookSuffix);
    }

    private function isProductEditScreen(object $screen, string $hookSuffix): bool
    {
        return in_array($hookSuffix, ['post.php', 'post-new.php'], true)
            && (($screen->post_type ?? '') === 'product' || ($screen->id ?? '') === 'product');
    }

    private function isCouponEditScreen(object $screen, string $hookSuffix): bool
    {
        return in_array($hookSuffix, ['post.php', 'post-new.php'], true)
            && (($screen->post_type ?? '') === 'shop_coupon' || ($screen->id ?? '') === 'shop_coupon');
    }

    private function isOrderEditScreen(object $screen, string $hookSuffix): bool
    {
        if (in_array($hookSuffix, ['post.php', 'post-new.php'], true)
            && (($screen->post_type ?? '') === 'shop_order' || ($screen->id ?? '') === 'shop_order')
        ) {
            return true;
        }

        $screenId = (string) ($screen->id ?? '');
        if ($screenId === '' || !str_contains($screenId, 'wc-orders')) {
            return false;
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        return in_array($action, ['edit', 'new'], true);
    }
}
