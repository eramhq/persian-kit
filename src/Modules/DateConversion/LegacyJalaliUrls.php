<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * Keeps Jalali addresses working while Jalali dates are off: a post link
 * such as /1403/05/12/my-post/ redirects to the post's Gregorian link, and
 * /1403/05/ still lists the posts of Mordad 1403.
 *
 * Parsi Date, WP Jalali and Persian Kit itself (with "Jalali dates in post
 * links") may have given posts these links, and search engines and other
 * sites keep them long after. A year below 1700 is never a Gregorian date
 * WordPress serves, so these requests are otherwise 404s and answering them
 * cannot change any other page. Nothing is rewritten: the site's own links
 * stay Gregorian.
 */
class LegacyJalaliUrls
{
    private JalaliDateArchive $archive;
    private JalaliPermalinks $permalinks;

    public function __construct(JalaliDateArchive $archive, JalaliPermalinks $permalinks)
    {
        $this->archive = $archive;
        $this->permalinks = $permalinks;
    }

    public function register(): void
    {
        // Resolves the Jalali date in the request to its Gregorian range.
        $this->archive->register();
        // The redirect to the Gregorian link, and the old-slug and cut-off
        // address lookups under a Jalali date.
        $this->permalinks->register();
    }
}
