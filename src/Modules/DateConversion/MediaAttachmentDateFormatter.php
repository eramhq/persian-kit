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
     * @return array<string, mixed>
     */
    public function filterAttachmentData(array $response, object $attachment, mixed $meta = null): array
    {
        if (!isset($response['dateFormatted']) || empty($attachment->post_date)) {
            return $response;
        }

        // Same format string core uses for dateFormatted, so it keeps core's translation.
        // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
        $response['dateFormatted'] = JalaliFormatter::fromLocalMysql(__('F j, Y'), $attachment->post_date)
            ?? $response['dateFormatted'];

        return $response;
    }
}
