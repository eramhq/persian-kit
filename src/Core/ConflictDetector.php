<?php

namespace PersianKit\Core;

defined('ABSPATH') || exit;

class ConflictDetector
{
    private const KNOWN_CONFLICTS = [
        'wp-parsidate/wp-parsidate.php'             => 'WP-Parsidate',
        'persian-woocommerce/persian-woocommerce.php' => 'Persian WooCommerce',
        'wp-jalali/wp-jalali.php'                    => 'WP Jalali',
    ];

    public function detect(): array
    {
        $active = [];

        foreach (self::KNOWN_CONFLICTS as $slug => $name) {
            if (is_plugin_active($slug)) {
                $active[] = $name;
            }
        }

        return $active;
    }

    public function registerNotice(): void
    {
        add_action('admin_notices', function () {
            $conflicts = $this->detect();

            if (empty($conflicts)) {
                return;
            }

            $list = implode('، ', $conflicts);
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php esc_html_e('Persian Kit', 'persian-kit'); ?>:</strong>
                    <?php
                    printf(
                        /* translators: %s: comma-separated list of conflicting plugin names */
                        esc_html__('The following plugins have overlapping features and may conflict: %s. You can disable overlapping features in either plugin to avoid issues.', 'persian-kit'),
                        '<strong>' . esc_html($list) . '</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
        });
    }
}
