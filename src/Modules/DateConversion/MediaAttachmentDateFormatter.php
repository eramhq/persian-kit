<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

class MediaAttachmentDateFormatter
{
    public function register(): void
    {
        add_filter('wp_prepare_attachment_for_js', [$this, 'filterAttachmentData'], 10, 3);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public function filterAttachmentData(array $response, object $attachment, mixed $meta = null): array
    {
        if (!isset($response['dateFormatted']) || empty($attachment->post_date) || !ContentLanguage::displaysPersian()) {
            return $response;
        }

        $response['dateFormatted'] = JalaliFormatter::fromLocalMysql((string) get_option('date_format'), $attachment->post_date)
            ?? $response['dateFormatted'];

        return $response;
    }
}
