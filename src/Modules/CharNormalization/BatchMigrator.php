<?php

namespace PersianKit\Modules\CharNormalization;

defined('ABSPATH') || exit;

class BatchMigrator
{
    private const CURSOR_OPTION = 'persian_kit_normalize_cursor';

    private CharNormalizer $normalizer;

    public function __construct(CharNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function getCursor(): int
    {
        return (int) get_option(self::CURSOR_OPTION, 0);
    }

    public function setCursor(int $postId): void
    {
        update_option(self::CURSOR_OPTION, $postId, false);
    }

    public function clearCursor(): void
    {
        delete_option(self::CURSOR_OPTION);
    }

    /**
     * Count posts containing Arabic characters, grouped by post type.
     *
     * @param array<string> $postTypes
     * @return array<string, int>
     */
    public function countAffected(array $postTypes): array
    {
        if ($postTypes === []) {
            return [];
        }

        global $wpdb;

        $arabicPattern = '[\x{064A}\x{0643}\x{0660}-\x{0669}\x{0629}]';
        $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts}
             WHERE post_type IN ({$placeholders})
               AND post_status != 'auto-draft'
               AND (post_title REGEXP %s OR post_content REGEXP %s OR post_excerpt REGEXP %s)
             GROUP BY post_type",
            array_merge($postTypes, [$arabicPattern, $arabicPattern, $arabicPattern])
        ));

        $counts = array_fill_keys($postTypes, 0);
        foreach ($rows as $row) {
            $counts[$row->post_type] = (int) $row->cnt;
        }

        return $counts;
    }

    /**
     * Process one batch of posts starting from the cursor position.
     *
     * @param array<string> $postTypes
     */
    public function processBatch(array $postTypes, int $batchSize, bool $dryRun = false): BatchResult
    {
        if ($postTypes === []) {
            return new BatchResult(0, 0, $this->getCursor(), false);
        }

        global $wpdb;

        $cursor = $this->getCursor();
        $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));

        $query = $wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt
             FROM {$wpdb->posts}
             WHERE ID > %d
               AND post_type IN ({$placeholders})
               AND post_status != 'auto-draft'
             ORDER BY ID ASC
             LIMIT %d",
            array_merge([$cursor], $postTypes, [$batchSize])
        );

        $posts = $wpdb->get_results($query);

        if (empty($posts)) {
            return new BatchResult(0, 0, $cursor, false);
        }

        $modified = 0;
        $lastId = $cursor;

        foreach ($posts as $post) {
            $lastId = (int) $post->ID;

            $newTitle   = $this->normalizer->normalize($post->post_title);
            $newExcerpt = $this->normalizer->normalize($post->post_excerpt);
            $newContent = $this->normalizer->normalizeContent($post->post_content);

            $changed = $newTitle !== $post->post_title
                    || $newExcerpt !== $post->post_excerpt
                    || $newContent !== $post->post_content;

            if ($changed && !$dryRun) {
                $wpdb->update(
                    $wpdb->posts,
                    [
                        'post_title'   => $newTitle,
                        'post_content' => $newContent,
                        'post_excerpt' => $newExcerpt,
                    ],
                    ['ID' => $post->ID],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

                clean_post_cache($post->ID);
            }

            if ($changed) {
                $modified++;
            }
        }

        $hasMore = count($posts) === $batchSize;

        if (!$dryRun) {
            $this->setCursor($lastId);
        }

        return new BatchResult(count($posts), $modified, $lastId, $hasMore);
    }

    public function isComplete(): bool
    {
        global $wpdb;

        $cursor = $this->getCursor();
        $maxId = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");

        return $cursor >= $maxId;
    }
}
