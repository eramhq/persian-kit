<?php

namespace PersianKit\Service\Assets;

defined('ABSPATH') || exit;

class ViteHelper
{
    public static function readManifest(): ?array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        }

        $content = @file_get_contents(PERSIAN_KIT_DIR . 'public/app/.vite/manifest.json');

        if ($content === false) {
            $cache = false;
            return null;
        }

        $manifest = json_decode($content, true);
        $cache = is_array($manifest) ? $manifest : false;

        return $cache === false ? null : $cache;
    }

    public static function enqueueFromManifest(array $manifest, string $entry, string $handle): void
    {
        if (!isset($manifest[$entry])) {
            return;
        }

        $entryData = $manifest[$entry];
        $distUrl = PERSIAN_KIT_URL . 'public/app/';

        if (!empty($entryData['css'])) {
            foreach ($entryData['css'] as $i => $cssFile) {
                wp_enqueue_style(
                    $handle . '-css-' . $i,
                    $distUrl . $cssFile,
                    [],
                    PERSIAN_KIT_VERSION
                );
            }
        }

        $jsUrl = $distUrl . $entryData['file'];

        if (function_exists('wp_enqueue_script_module')) {
            wp_enqueue_script_module($handle, $jsUrl);
        } else {
            wp_enqueue_script($handle, $jsUrl, [], PERSIAN_KIT_VERSION, true);
        }
    }

    public static function isDevServer(): bool
    {
        return defined('PERSIAN_KIT_VITE_DEV') && PERSIAN_KIT_VITE_DEV;
    }
}
