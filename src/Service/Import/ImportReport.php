<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * The report of a source's imports: what was done, what was not imported
 * and why, and what needs attention, with links to each thing.
 */
class ImportReport
{
    public const PER_PAGE = 50;

    private ImportLog $log;
    private ImportState $state;

    public function __construct(ImportLog $log, ImportState $state)
    {
        $this->log = $log;
        $this->state = $state;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, counts: array<string, int>, pages: int, tips: list<string>}
     */
    public function page(Source $source, ?string $outcome, int $page, int $perPage = self::PER_PAGE): array
    {
        $runIds = $this->runIds($source);
        $result = $this->log->report($runIds, $outcome, $page, $perPage);

        $job = ImportJob::load();
        $job = $job !== null && $job->source === $source->key() ? $job->toPublic() : [];

        return [
            'rows'   => array_map([$this, 'describe'], $result['rows']),
            'total'  => $result['total'],
            'counts' => $this->log->counts($runIds),
            'pages'  => (int) ceil($result['total'] / max(1, $perPage)),
            'tips'   => $source instanceof HasReportTips ? $source->reportTips($job) : [],
        ];
    }

    /**
     * The whole report as CSV, with a byte-order mark so spreadsheet apps
     * read the Persian text as UTF-8.
     */
    public function csv(Source $source): string
    {
        $csv = "\xEF\xBB\xBF" . self::csvLine([
            __('Outcome', 'persian-kit'),
            __('Part', 'persian-kit'),
            __('Item', 'persian-kit'),
            __('Field', 'persian-kit'),
            __('Before', 'persian-kit'),
            __('After', 'persian-kit'),
            __('Reason', 'persian-kit'),
            __('Link', 'persian-kit'),
            __('Date', 'persian-kit'),
        ]);

        $page = 1;
        $runIds = $this->runIds($source);
        do {
            $result = $this->log->report($runIds, null, $page, 500);
            foreach ($result['rows'] as $row) {
                $described = $this->describe($row);
                $csv .= self::csvLine([
                    $described['outcome_label'],
                    $row->task,
                    $described['object'],
                    $row->field,
                    $described['before'],
                    $described['after'],
                    $row->reason,
                    $described['url'],
                    $row->createdAt,
                ]);
            }
            $page++;
        } while (count($result['rows']) === 500);

        return $csv;
    }

    /**
     * One CSV line, every field quoted. A field starting with =, +, - or @
     * gets a leading apostrophe, so a spreadsheet never runs it as a formula.
     *
     * @param list<string> $fields
     */
    public static function csvLine(array $fields): string
    {
        return implode(',', array_map(static function (string $field): string {
            if ($field !== '' && in_array($field[0], ['=', '+', '-', '@'], true)) {
                $field = "'" . $field;
            }

            return '"' . str_replace('"', '""', $field) . '"';
        }, $fields)) . "\r\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(LogRow $row): array
    {
        [$object, $url] = $this->objectLabel($row);

        return $row->toArray() + [
            'outcome_label' => self::outcomeLabel($row->outcome),
            'object'        => $object,
            'url'           => $url,
            'before'        => self::text($row->oldValue),
            'after'         => self::text($row->newValue),
        ];
    }

    public static function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            ImportLog::CHANGED      => __('Done', 'persian-kit'),
            ImportLog::UNCHANGED    => __('Already right', 'persian-kit'),
            ImportLog::ATTENTION    => __('Needs attention', 'persian-kit'),
            ImportLog::NOT_IMPORTED => __('Not imported', 'persian-kit'),
            ImportLog::RESTORED     => __('Put back', 'persian-kit'),
            ImportLog::KEPT         => __('Kept: changed since the import', 'persian-kit'),
            ImportLog::REVERTED     => __('Undone', 'persian-kit'),
            default                 => $outcome,
        };
    }

    /**
     * @return list<string>
     */
    private function runIds(Source $source): array
    {
        $runIds = $this->state->get($source->key())['run_ids'];

        $job = ImportJob::load();
        if ($job !== null && $job->source === $source->key() && !in_array($job->runId, $runIds, true)) {
            $runIds[] = $job->runId;
        }

        return $runIds;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function objectLabel(LogRow $row): array
    {
        $id = $row->objectId;

        switch ($row->objectType) {
            case 'user':
                $user = get_userdata($id);

                /* translators: %d: user ID. */
                return [$user ? $user->display_name : sprintf(__('User %d', 'persian-kit'), $id), get_edit_user_link($id)];
            case 'order':
                $url = function_exists('wc_get_order') && ($order = wc_get_order($id)) ? (string) $order->get_edit_order_url() : '';

                /* translators: %d: order number. */
                return [sprintf(__('Order #%d', 'persian-kit'), $id), $url];
            case 'post':
                $title = get_the_title($id);

                /* translators: %d: post ID. */
                return [$title !== '' ? $title : sprintf(__('Post %d', 'persian-kit'), $id), (string) get_edit_post_link($id, 'raw')];
            case 'zone':
                /* translators: %d: shipping zone ID. */
                return [sprintf(__('Shipping zone %d', 'persian-kit'), $id), admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $id)];
            case 'setting':
                return [$row->field, ''];
            default:
                return [$id > 0 ? $row->objectType . ' ' . $id : $row->objectType, ''];
        }
    }

    private static function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? __('On', 'persian-kit') : __('Off', 'persian-kit');
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '' : $json;
    }
}
