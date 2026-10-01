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
                    'description' => 'Publish date as Jalali (Shamsi) YYYY-MM-DDTHH:MM:SS in the site timezone. Laid out like ISO 8601 but not ISO 8601: the year, month and day are Jalali.',
                    'context'     => ['view', 'embed'],
                    'readonly'    => true,
                ],
            ]);

            register_rest_field($postType, 'date_modified_jalali', [
                'get_callback' => [$this, 'getModifiedDateJalali'],
                'schema'       => [
                    'type'        => ['string', 'null'],
                    'description' => 'Last-modified date as Jalali (Shamsi) YYYY-MM-DDTHH:MM:SS in the site timezone. Laid out like ISO 8601 but not ISO 8601: the year, month and day are Jalali.',
                    'context'     => ['view', 'embed'],
                    'readonly'    => true,
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $post Prepared REST response data.
     */
    public function getDateJalali(array $post): ?string
    {
        return $this->formatJalali($post['date_gmt'] ?? null, $post['date'] ?? null);
    }

    /**
     * @param array<string, mixed> $post Prepared REST response data.
     */
    public function getModifiedDateJalali(array $post): ?string
    {
        return $this->formatJalali($post['modified_gmt'] ?? null, $post['modified'] ?? null);
    }

    /**
     * Drafts have no GMT date (null), so the local date is the fallback.
     */
    private function formatJalali(?string $gmt, ?string $local): ?string
    {
        return JalaliFormatter::fromGmtMysql('Y-m-d\\TH:i:s', $gmt)
            ?? JalaliFormatter::fromLocalMysql('Y-m-d\\TH:i:s', $local);
    }
}
