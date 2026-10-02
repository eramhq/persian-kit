<?php

namespace PersianKit\Modules\Utilities;

defined('ABSPATH') || exit;

/**
 * Percent-encodes the Persian letters of slugs in the links WordPress builds,
 * so /برنامه/ is linked as /%D8%A8%D8%B1.../, the form core gives Persian
 * slugs. The slug itself keeps its letters in the database and the editor, and
 * browsers show the link decoded.
 *
 * A raw UTF-8 link breaks wherever WordPress runs it through parse_url(), as
 * redirect_canonical() does for /?p=123: in some locales (C.UTF-8 on macOS)
 * PHP takes bytes 0x80–0x9F for control characters and turns them into "_",
 * so /برنامه/ became /بر_ا__/, a 404. Already-encoded links and the host are
 * left alone.
 */
class PermalinkEncoder
{
    private const LINK_FILTERS = ['post_link', 'page_link', 'post_type_link', 'attachment_link', 'term_link'];

    public function register(): void
    {
        foreach (self::LINK_FILTERS as $filter) {
            // Late, so links other plugins rewrite are encoded too.
            add_filter($filter, [self::class, 'encode'], 99);
        }
    }

    /**
     * Percent-encodes every non-ASCII byte after the scheme and host.
     */
    public static function encode(mixed $url): mixed
    {
        if (!is_string($url) || !preg_match('/[\x80-\xFF]/', $url)) {
            return $url;
        }

        // An internationalized host stays as it is; only the path, query and fragment are encoded.
        $start = preg_match('#^[a-z][a-z0-9+.\-]*://[^/?\#]*#i', $url, $authority) ? strlen($authority[0]) : 0;

        return substr($url, 0, $start) . preg_replace_callback(
            '/[\x80-\xFF]+/',
            static fn (array $bytes): string => rawurlencode($bytes[0]),
            substr($url, $start)
        );
    }
}
