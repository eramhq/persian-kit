<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

class MediaAttachmentDateFormatter
{
    public function register(): void
    {
        add_filter('wp_prepare_attachment_for_js', [$this, 'filterAttachmentData'], 10, 3);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function filterAttachmentData(array $response, object $attachment, mixed $meta = null): array
    {
        if (!isset($response['dateFormatted']) || empty($attachment->post_date)) {
            return $response;
        }

        $response['dateFormatted'] = JalaliFormatter::format(__('F j, Y'), $attachment->post_date);

        return $response;
    }
}
