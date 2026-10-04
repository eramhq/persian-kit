<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DateConversion\JalaliPeriod;

defined('ABSPATH') || exit;

/**
 * Stats by Jalali month, season or year in WooCommerce Analytics. The
 * database groups orders by Gregorian month, so a bar labelled مهر would
 * hold 1 October to 31 October. When Analytics asks a stats endpoint for
 * months, quarters or years with the Jalali flag (added by
 * woocommerce-analytics-dates.js), each Jalali period on the page is asked
 * of WooCommerce on its own, and the answers make up the reply.
 *
 * Every period's numbers are WooCommerce's own, with its filters, segments
 * and cache: counts of unique customers and averages are queried, never
 * added up. Any /wc-analytics/…/stats route that replies with totals and
 * intervals is covered, so reports added by extensions are too.
 */
class WooAnalyticsIntervals
{
    /** Query parameter, set to 'jalali', that asks for Jalali periods. */
    public const FLAG = 'persian_kit_calendar';

    /** WooCommerce intervals and the Jalali months in each. */
    private const MONTHS = ['month' => 1, 'quarter' => 3, 'year' => 12];

    /** Most periods a range is split into, against runaway loops. */
    private const MAX_PERIODS = 5000;

    public function register(): void
    {
        add_filter('rest_dispatch_request', [$this, 'dispatch'], 10, 2);
    }

    /**
     * Runs after the request's permissions and parameters were checked.
     *
     * @param mixed $result Null, unless a filter answered already.
     * @param mixed $request
     * @return mixed
     */
    public function dispatch($result, $request)
    {
        if ($result !== null || !$request instanceof \WP_REST_Request || !self::asksForJalaliPeriods($request)) {
            return $result;
        }

        /**
         * Whether a stats endpoint's months, quarters and years are grouped
         * by Jalali period. Return false for an endpoint whose intervals are
         * not dates of orders.
         *
         * @param bool             $enabled
         * @param string           $route   Such as /wc-analytics/reports/revenue/stats.
         * @param \WP_REST_Request $request
         */
        if (!apply_filters('persian_kit_analytics_jalali_intervals', true, $request->get_route(), $request)) {
            return $result;
        }

        return $this->respond($request) ?? $result;
    }

    public static function asksForJalaliPeriods(\WP_REST_Request $request): bool
    {
        return $request->get_method() === 'GET'
            && $request->get_param(self::FLAG) === 'jalali'
            && isset(self::MONTHS[(string) $request->get_param('interval')])
            && preg_match('#^/wc-analytics/.+/stats$#', $request->get_route()) === 1;
    }

    /**
     * The reply with Jalali intervals, the error reply WooCommerce gave, or
     * null to leave the request to WooCommerce: no range, or a reply of
     * another shape.
     */
    private function respond(\WP_REST_Request $request): ?\WP_REST_Response
    {
        $after = self::localDateTime($request->get_param('after'));
        $before = self::localDateTime($request->get_param('before'));
        if ($after === null || $before === null || $after > $before) {
            return null;
        }

        $periods = self::periods($after, $before, (string) $request->get_param('interval'));
        if ($periods === null) {
            return null;
        }
        $count = count($periods);

        $totals = $this->totals($request, $after, $before);
        if (!is_array($totals)) {
            return $totals;
        }

        $perPage = max(1, (int) ($request->get_param('per_page') ?: 10));
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $orderBy = (string) ($request->get_param('orderby') ?: 'date');
        $descending = $request->get_param('order') !== 'asc';

        if ($orderBy === 'date') {
            if ($descending) {
                $periods = array_reverse($periods);
            }
            $periods = array_slice($periods, ($page - 1) * $perPage, $perPage);
        }

        $intervals = [];
        foreach ($periods as $period) {
            $subtotals = $this->totals($request, $period['start'], $period['end']);
            if (!is_array($subtotals)) {
                return $subtotals;
            }

            $intervals[] = self::interval($period, $subtotals);
        }

        // Sorted by a number: every period is needed to know the page.
        if ($orderBy !== 'date') {
            usort($intervals, static function (array $a, array $b) use ($orderBy, $descending): int {
                $order = ((float) ($a['subtotals']->$orderBy ?? 0)) <=> ((float) ($b['subtotals']->$orderBy ?? 0));

                return $descending ? -$order : $order;
            });
            $intervals = array_slice($intervals, ($page - 1) * $perPage, $perPage);
        }

        $response = new \WP_REST_Response(['totals' => $totals, 'intervals' => $intervals]);
        $response->header('X-WP-Total', (string) $count);
        $response->header('X-WP-TotalPages', (string) (int) ceil($count / $perPage));

        return $response;
    }

    /**
     * WooCommerce's totals for one range: the endpoint asked again with that
     * range and without the flag. An array of totals, the error WooCommerce
     * gave, or null when its reply has no totals and intervals.
     *
     * @return array<string, mixed>|\WP_REST_Response|null
     */
    private function totals(\WP_REST_Request $request, \DateTimeImmutable $after, \DateTimeImmutable $before)
    {
        $params = $request->get_query_params();
        unset($params[self::FLAG]);

        $inner = new \WP_REST_Request('GET', $request->get_route());
        $inner->set_query_params(array_merge($params, [
            'after'    => $after->format('Y-m-d\TH:i:s'),
            'before'   => $before->format('Y-m-d\TH:i:s'),
            // The fewest intervals the range can have; only the totals are read.
            'interval' => 'year',
            'per_page' => 1,
            'page'     => 1,
        ]));

        $response = rest_do_request($inner);
        if ($response->is_error()) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data) || !array_key_exists('totals', $data) || !isset($data['intervals']) || !is_array($data['intervals'])) {
            return null;
        }

        return is_array($data['totals']) ? $data['totals'] : [];
    }

    /**
     * The Jalali months, seasons or years from after to before, the first
     * and last cut to the range, in order.
     *
     * @return list<array{id: string, start: \DateTimeImmutable, end: \DateTimeImmutable}>|null
     */
    public static function periods(\DateTimeImmutable $after, \DateTimeImmutable $before, string $interval): ?array
    {
        $months = self::MONTHS[$interval] ?? null;
        if ($months === null) {
            return null;
        }

        $periods = [];
        $start = $after;

        while ($start <= $before) {
            if (count($periods) >= self::MAX_PERIODS) {
                return null;
            }

            $date = JalaliPeriod::fromGregorian($start);
            $firstMonth = $date['jm'] - ($date['jm'] - 1) % $months;
            $range = JalaliPeriod::range($date['jy'], $firstMonth + $months - 1);
            if ($range === null) {
                return null;
            }

            $end = new \DateTimeImmutable($range['end'], $start->getTimezone());
            $periods[] = [
                'id'    => match ($interval) {
                    'month'   => sprintf('%04d-%02d', $date['jy'], $firstMonth),
                    'quarter' => sprintf('%04d-%d', $date['jy'], intdiv($firstMonth - 1, 3) + 1),
                    default   => (string) $date['jy'],
                },
                'start' => $start,
                'end'   => min($end, $before),
            ];

            $start = $end->modify('+1 second');
        }

        return $periods;
    }

    /**
     * @param array{id: string, start: \DateTimeImmutable, end: \DateTimeImmutable} $period
     * @param array<string, mixed>                                                 $subtotals
     * @return array<string, mixed>
     */
    private static function interval(array $period, array $subtotals): array
    {
        $utc = new \DateTimeZone('UTC');

        return [
            'interval'       => $period['id'],
            'date_start'     => $period['start']->format('Y-m-d H:i:s'),
            'date_start_gmt' => $period['start']->setTimezone($utc)->format('Y-m-d H:i:s'),
            'date_end'       => $period['end']->format('Y-m-d H:i:s'),
            'date_end_gmt'   => $period['end']->setTimezone($utc)->format('Y-m-d H:i:s'),
            'subtotals'      => (object) $subtotals,
        ];
    }

    /**
     * A date as WooCommerce reads after and before: site time unless it
     * names its own offset.
     */
    private static function localDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, wp_timezone()))->setTimezone(wp_timezone());
        } catch (\Exception) {
            return null;
        }
    }
}
