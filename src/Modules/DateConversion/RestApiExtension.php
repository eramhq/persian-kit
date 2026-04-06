<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

class RestApiExtension
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerFields']);
    }

    public function registerFields(): void
    {
        $postTypes = get_post_types(['show_in_rest' => true], 'names');

        foreach ($postTypes as $postType) {
            register_rest_field($postType, 'date_jalali', [
                'get_callback' => [$this, 'getDateJalali'],
                'schema'       => [
                    'type'        => ['string', 'null'],
                    'description' => 'Jalali (Shamsi) date in ISO format.',
                    'context'     => ['view', 'embed'],
                    'readonly'    => true,
                ],
            ]);

            register_rest_field($postType, 'date_modified_jalali', [
                'get_callback' => [$this, 'getModifiedDateJalali'],
                'schema'       => [
                    'type'        => ['string', 'null'],
                    'description' => 'Jalali (Shamsi) modified date in ISO format.',
                    'context'     => ['view', 'embed'],
                    'readonly'    => true,
                ],
            ]);
        }
    }

    public function getDateJalali(array $post): ?string
    {
        $timestamp = $post['date_gmt'] ?? $post['date'] ?? null;
        return $timestamp ? $this->formatIsoJalali($timestamp) : null;
    }

    public function getModifiedDateJalali(array $post): ?string
    {
        $timestamp = $post['modified_gmt'] ?? $post['modified'] ?? null;
        return $timestamp ? $this->formatIsoJalali($timestamp) : null;
    }

    private function formatIsoJalali(string $timestamp): string
    {
        return JalaliFormatter::format('Y-m-d', $timestamp) . 'T' . JalaliFormatter::format('H:i:s', $timestamp);
    }
}
