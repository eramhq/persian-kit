<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Parsi Date's archive and calendar blocks become WordPress's in posts,
 * pages, templates and template parts edited in the Site Editor, synced
 * patterns, navigation menus and block widgets. Saved directly, so no
 * revision is made and no save hook runs.
 */
class BlockTask extends AbstractTask
{
    public const KEY = 'blocks';
    private const NEEDLE = '<!-- wp:wp-parsidate/';

    private BlockRewriter $rewriter;

    public function __construct(?BlockRewriter $rewriter = null)
    {
        $this->rewriter = $rewriter ?? new BlockRewriter();
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Archive and calendar blocks', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $posts = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE %s", '%' . $wpdb->esc_like(self::NEEDLE) . '%'));

        return $posts + count($this->widgetBlocks());
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach ($this->ids(0, $limit) as $postId) {
            $samples[] = [
                'label'  => get_the_title($postId) !== '' ? get_the_title($postId) : (string) get_post_type($postId),
                'before' => __('Parsi Date blocks', 'persian-kit'),
                'after'  => __('WordPress\'s Archives and Calendar blocks', 'persian-kit'),
            ];
        }

        return $samples;
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        global $wpdb;

        $ids = $this->ids((int) $cursor, $limit);
        foreach ($ids as $postId) {
            $this->guarded($context, 'post', $postId, function () use ($context, $postId, $wpdb): void {
                $old = (string) get_post_field('post_content', $postId, 'raw');
                $new = $this->rewriter->rewrite($old, __('Archive', 'persian-kit'), __('Calendar', 'persian-kit'));
                foreach ($this->rewriter->skipped() as $reason) {
                    $context->log->attention($context, $this->key(), 'post', $postId, 'post_content', $reason);
                }
                if ($new === $old) {
                    return;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $postId]);
                clean_post_cache($postId);
                $context->log->changed($context, $this->key(), 'post', $postId, 'post_content', $old, $new);
            });
        }

        $done = count($ids) < $limit;
        if ($done) {
            $this->convertWidgetBlocks($context);
        }

        return new TaskBatch(count($ids), $ids === [] ? $cursor : end($ids), $done);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        if ($row->objectType === 'option') {
            $instances = get_option('widget_block', []);
            $number = (int) substr($row->field, strlen('widget_block-'));
            if (!is_array($instances) || ($instances[$number]['content'] ?? null) !== $row->newValue) {
                return ImportLog::KEPT;
            }
            $instances[$number]['content'] = (string) $row->oldValue;
            update_option('widget_block', $instances);

            return ImportLog::RESTORED;
        }

        if ((string) get_post_field('post_content', $row->objectId, 'raw') !== $row->newValue) {
            return ImportLog::KEPT;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($wpdb->posts, ['post_content' => (string) $row->oldValue], ['ID' => $row->objectId]);
        clean_post_cache($row->objectId);

        return ImportLog::RESTORED;
    }

    private function convertWidgetBlocks(ImportContext $context): void
    {
        $instances = get_option('widget_block', []);
        if (!is_array($instances)) {
            return;
        }

        $changed = false;
        foreach ($this->widgetBlocks() as $number) {
            $old = (string) $instances[$number]['content'];
            $new = $this->rewriter->rewrite($old, __('Archive', 'persian-kit'), __('Calendar', 'persian-kit'));
            foreach ($this->rewriter->skipped() as $reason) {
                $context->log->attention($context, $this->key(), 'option', 0, 'widget_block-' . $number, $reason);
            }
            if ($new !== $old) {
                $instances[$number]['content'] = $new;
                $context->log->changed($context, $this->key(), 'option', 0, 'widget_block-' . $number, $old, $new);
                $changed = true;
            }
        }

        if ($changed) {
            update_option('widget_block', $instances);
        }
    }

    /**
     * @return list<int>
     */
    private function ids(int $after, int $limit): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE %s AND ID > %d ORDER BY ID ASC LIMIT %d",
            '%' . $wpdb->esc_like(self::NEEDLE) . '%',
            $after,
            $limit
        )));
    }

    /**
     * Block widgets holding Parsi Date blocks.
     *
     * @return list<int>
     */
    private function widgetBlocks(): array
    {
        $instances = get_option('widget_block', []);
        $numbers = [];
        foreach (is_array($instances) ? $instances : [] as $number => $instance) {
            if (is_int($number) && is_array($instance) && BlockRewriter::hasBlocks((string) ($instance['content'] ?? ''))) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }
}
