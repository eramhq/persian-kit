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
        [$before, $after] = self::change($row);

        return $row->toArray() + [
            'outcome_label' => self::outcomeLabel($row->outcome),
            'object'        => $object,
            'url'           => $url,
            'before'        => $before,
            'after'         => $after,
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
                $name = class_exists('WC_Shipping_Zone') ? (new \WC_Shipping_Zone($id))->get_zone_name() : '';

                /* translators: %d: shipping zone ID. */
                return [$name !== '' ? $name : sprintf(__('Shipping zone %d', 'persian-kit'), $id), admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $id)];
            case 'widget':
                return [__('Widget', 'persian-kit'), admin_url('widgets.php')];
            case 'option':
                return [$row->field, ''];
            case 'setting':
                return [self::settingLabels()[$row->field] ?? $row->field, ''];
            default:
                return [$id > 0 ? $row->objectType . ' ' . $id : $row->objectType, ''];
        }
    }

    /**
     * Before and after, short enough to read: widgets by id, content as
     * what it holds rather than its markup.
     *
     * @return array{0: string, 1: string}
     */
    private static function change(LogRow $row): array
    {
        if ($row->task === 'widgets' && is_array($row->oldValue) && is_array($row->newValue)) {
            return [(string) ($row->oldValue['id'] ?? ''), (string) ($row->newValue['id'] ?? '')];
        }

        if ($row->task === 'blocks') {
            return $row->outcome === ImportLog::CHANGED
                ? [__('Parsi Date blocks', 'persian-kit'), __('WordPress\'s Archives and Calendar blocks', 'persian-kit')]
                : ['', ''];
        }

        if ($row->task === 'shipping_zones' && is_array($row->oldValue) && is_array($row->newValue)) {
            $codes = static fn (array $locations): string => implode('، ', array_map(static fn ($location): string => is_array($location) ? (string) ($location['code'] ?? '') : '', $locations));

            return [$codes($row->oldValue), $codes($row->newValue)];
        }

        if ($row->task === 'acf_fields' && is_array($row->oldValue) && is_array($row->newValue)) {
            return [(string) ($row->oldValue['type'] ?? ''), (string) ($row->newValue['type'] ?? '')];
        }

        return [self::text($row->oldValue), self::text($row->newValue)];
    }

    /**
     * Persian Kit's settings by path, as the settings page names them.
     *
     * @return array<string, string>
     */
    private static function settingLabels(): array
    {
        return [
            'date_conversion.enabled'              => __('Jalali dates', 'persian-kit'),
            'date_conversion.global_conversion'    => __('Convert every date (advanced)', 'persian-kit'),
            'date_conversion.jalali_permalinks'    => __('Jalali dates in post links', 'persian-kit'),
            'digit_conversion.enabled'             => __('Persian digits', 'persian-kit'),
            'digit_conversion.dates'               => __('Persian digits in dates', 'persian-kit'),
            'digit_conversion.numbers'             => __('Persian digits in counts and numbers', 'persian-kit'),
            'digit_conversion.prices'              => __('Persian digits in prices', 'persian-kit'),
            'digit_conversion.emails'              => __('Persian digits in WooCommerce emails', 'persian-kit'),
            'char_normalization.enabled'           => __('Persian ی and ک', 'persian-kit'),
            'admin_font.enabled'                   => __('Admin font', 'persian-kit'),
            'admin_font.font'                      => __('Admin font', 'persian-kit'),
            'woocommerce.enabled'                  => __('WooCommerce', 'persian-kit'),
            'woocommerce.checkout_normalize'       => __('Fix what customers type at checkout', 'persian-kit'),
            'woocommerce.checkout_validate'        => __('Check what customers type at checkout', 'persian-kit'),
            'woocommerce.city_select'              => __('City suggestions', 'persian-kit'),
            'woocommerce.allowed_states'           => __('Provinces you ship to', 'persian-kit'),
            'woocommerce.short_checkout'           => __('Shorter checkout when nothing needs shipping', 'persian-kit'),
            'woocommerce.dates_admin'              => __('Jalali dates in the shop admin', 'persian-kit'),
            'woocommerce.dates_analytics'          => __('Jalali dates in WooCommerce Analytics', 'persian-kit'),
            'woocommerce.call_for_price'           => __('Text instead of an empty price', 'persian-kit'),
            'woocommerce.call_for_price_text'      => __('Text on the product page', 'persian-kit'),
            'woocommerce.call_for_price_list_text' => __('Text in the shop and other lists', 'persian-kit'),
            'acf.enabled'                          => __('ACF', 'persian-kit'),
        ];
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
