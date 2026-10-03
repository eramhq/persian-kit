<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

defined('ABSPATH') || exit;

/**
 * Swaps Parsi Date's archive and calendar blocks for WordPress's own, which
 * Persian Kit shows in Jalali. Only the block delimiters change; the rest
 * of the content is left byte for byte.
 *
 * wp-parsidate/archive {title, postType, type, displaySelect, displayCount}
 * becomes a heading and core/archives {type, displayAsDropdown,
 * showPostCounts}; wp-parsidate/calendar {title, postType, theme} becomes a
 * heading and core/calendar. A block for another post type is left alone:
 * WordPress's blocks list posts only.
 */
final class BlockRewriter
{
    private const PATTERN = '#<!--\s+wp:wp-parsidate/(archive|calendar)(\s+(\{.*?\}))?\s+(/-->|-->(.*?)<!--\s+/wp:wp-parsidate/\1\s+-->)#s';

    /** @var list<string> Why blocks were left alone. */
    private array $skipped = [];

    public static function hasBlocks(string $content): bool
    {
        return str_contains($content, '<!-- wp:wp-parsidate/');
    }

    /**
     * @param string $archiveTitle  The title Parsi Date shows when the block has none.
     * @param string $calendarTitle The same for the calendar.
     */
    public function rewrite(string $content, string $archiveTitle = 'Archive', string $calendarTitle = 'Calendar'): string
    {
        $this->skipped = [];

        return (string) preg_replace_callback(self::PATTERN, function (array $match) use ($archiveTitle, $calendarTitle): string {
            $attributes = $match[3] !== '' ? json_decode($match[3], true) : [];
            $attributes = is_array($attributes) ? $attributes : [];

            $postType = $attributes['postType'] ?? 'post';
            if ($postType !== 'post') {
                /* translators: %s: post type. */
                $this->skipped[] = sprintf(__('A Parsi Date block for "%s" was left as it is: WordPress\'s archive and calendar blocks list posts only.', 'persian-kit'), (string) $postType);

                return $match[0];
            }

            $isArchive = $match[1] === 'archive';
            $title = array_key_exists('title', $attributes) ? (string) $attributes['title'] : ($isArchive ? $archiveTitle : $calendarTitle);

            $core = [];
            if ($isArchive) {
                if (($attributes['type'] ?? 'monthly') !== 'monthly') {
                    $core['type'] = (string) $attributes['type'];
                }
                if (!empty($attributes['displaySelect'])) {
                    $core['displayAsDropdown'] = true;
                }
                if (!empty($attributes['displayCount'])) {
                    $core['showPostCounts'] = true;
                }
            }
            foreach (['className', 'align'] as $kept) {
                if (isset($attributes[$kept]) && is_string($attributes[$kept]) && $attributes[$kept] !== '') {
                    $core[$kept] = $attributes[$kept];
                }
            }

            $block = '<!-- wp:' . ($isArchive ? 'archives' : 'calendar') . ($core === [] ? '' : ' ' . self::json($core)) . ' /-->';

            return ($title === '' ? '' : self::heading($title) . "\n\n") . $block;
        }, $content);
    }

    /**
     * Why the last rewrite left blocks alone.
     *
     * @return list<string>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    public static function heading(string $title): string
    {
        return '<!-- wp:heading -->' . "\n" . '<h2 class="wp-block-heading">' . esc_html($title) . '</h2>' . "\n" . '<!-- /wp:heading -->';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function json(array $attributes): string
    {
        // As the block editor writes attributes: no escaped slashes or Unicode.
        return (string) wp_json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
