<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Parsi Date's archive and calendar widgets become WordPress's own, in the
 * same place: a monthly archive becomes the Archives widget, a yearly or
 * daily one a block widget with a heading and the Archives block, and the
 * calendar the Calendar widget (its colour theme is dropped). Persian Kit
 * shows them in Jalali. The old widgets' settings are kept for undo.
 *
 * Once Parsi Date is inactive, WordPress moves its widgets to "Inactive
 * widgets" the first time someone opens the Widgets screen; the snapshot
 * Review took puts them back where they were.
 */
class WidgetTask extends AbstractTask
{
    public const KEY = 'widgets';

    /** Widget id base => [kind, format]: 64 for 6.4's keys, legacy for 6.0 to 6.3 and 5.x. */
    public const BASES = [
        'wp_parsidate_archive'                     => ['archive', '64'],
        'wp_parsidate_calendar'                    => ['calendar', '64'],
        'wpparsidate\widget\parsidatearchivewidget'  => ['archive', 'legacy'],
        'wpparsidate\widget\parsidatecalendarwidget' => ['calendar', 'legacy'],
        'parsidate_archive'                        => ['archive', 'legacy'],
        'parsidate_calendar'                       => ['calendar', 'legacy'],
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Archive and calendar widgets', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return count($this->widgets($context));
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $samples = [];
        foreach (array_slice($this->widgets($context), 0, $limit) as $widget) {
            $samples[] = [
                'label'  => $widget['sidebar'] !== null ? self::sidebarName($widget['sidebar']) : __('Inactive widgets', 'persian-kit'),
                'before' => $widget['kind'] === 'archive' ? __('Parsi Date archive', 'persian-kit') : __('Parsi Date calendar', 'persian-kit'),
                'after'  => $widget['kind'] === 'archive' ? __('Archives', 'persian-kit') : __('Calendar', 'persian-kit'),
            ];
        }

        return $samples;
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $widgets = $this->widgets($context);

        foreach ($widgets as $widget) {
            $this->guarded($context, 'widget', 0, function () use ($context, $widget): void {
                $this->convert($context, $widget);
            });
        }

        return new TaskBatch(count($widgets), null, true);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $new = is_array($row->newValue) ? $row->newValue : [];
        $old = is_array($row->oldValue) ? $row->oldValue : [];
        $sidebars = self::sidebars();
        $sidebar = (string) ($new['sidebar'] ?? '');
        $position = array_search($new['id'] ?? null, $sidebars[$sidebar] ?? [], true);

        if ($position === false || !isset($old['id'])) {
            return ImportLog::KEPT;
        }

        // Out of "Inactive widgets", if WordPress put it there.
        foreach ($sidebars as $name => $ids) {
            if (is_array($ids)) {
                $sidebars[$name] = array_values(array_diff($ids, [$old['id']]));
            }
        }
        $sidebars[$sidebar][$position] = $old['id'];
        wp_set_sidebars_widgets($sidebars);

        [$base, $number] = self::split((string) $new['id']);
        $instances = get_option('widget_' . $base, []);
        if (is_array($instances)) {
            unset($instances[$number]);
            update_option('widget_' . $base, $instances);
        }

        return ImportLog::RESTORED;
    }

    /**
     * Parsi Date's widgets still placed in a sidebar, or in "Inactive
     * widgets", with where they belong.
     *
     * @return list<array{id: string, base: string, number: int, kind: string, format: string, instance: array<string, mixed>, sidebar: ?string, position: int, from: ?string}>
     */
    private function widgets(ImportContext $context): array
    {
        $current = self::sidebars();
        $snapshot = is_array($context->snapshot['sidebars_widgets'] ?? null) ? $context->snapshot['sidebars_widgets'] : [];
        $widgets = [];

        foreach (self::BASES as $base => [$kind, $format]) {
            $instances = get_option('widget_' . $base, []);
            foreach (is_array($instances) ? $instances : [] as $number => $instance) {
                if (!is_int($number) || !is_array($instance)) {
                    continue;
                }

                $id = $base . '-' . $number;
                $placed = self::find($current, $id);
                if ($placed === null) {
                    // Not placed anywhere: a leftover instance, nothing to convert.
                    continue;
                }

                [$sidebar, $position] = $placed;
                $from = null;
                if ($sidebar === 'wp_inactive_widgets') {
                    $before = self::find($snapshot, $id);
                    $from = $sidebar;
                    [$sidebar, $position] = $before !== null && $before[0] !== 'wp_inactive_widgets' ? $before : [null, $position];
                }

                $widgets[] = compact('id', 'base', 'number', 'kind', 'format', 'instance', 'sidebar', 'position', 'from');
            }
        }

        return $widgets;
    }

    /**
     * @param array{id: string, base: string, number: int, kind: string, format: string, instance: array<string, mixed>, sidebar: ?string, position: int, from: ?string} $widget
     */
    private function convert(ImportContext $context, array $widget): void
    {
        if ($widget['sidebar'] === null) {
            $context->log->attention($context, $this->key(), 'widget', 0, $widget['id'], __('WordPress moved it to "Inactive widgets" before the switch, and there is no record of where it was. Add WordPress\'s Archives or Calendar widget where you want it.', 'persian-kit'));

            return;
        }

        $settings = self::settings($widget);
        if ($settings['post_type'] !== 'post') {
            $context->log->attention($context, $this->key(), 'widget', 0, $widget['id'], sprintf(
                /* translators: %s: post type. */
                __('It lists "%s": WordPress\'s archive and calendar widgets list posts only. Left as it is.', 'persian-kit'),
                $settings['post_type']
            ));

            return;
        }

        if ($widget['kind'] === 'calendar') {
            $newId = self::addInstance('calendar', ['title' => $settings['title']]);
        } elseif ($settings['type'] === 'monthly') {
            $newId = self::addInstance('archives', ['title' => $settings['title'], 'count' => $settings['count'] ? 1 : 0, 'dropdown' => $settings['dropdown'] ? 1 : 0]);
        } else {
            $attributes = ['type' => $settings['type']];
            if ($settings['dropdown']) {
                $attributes['displayAsDropdown'] = true;
            }
            if ($settings['count']) {
                $attributes['showPostCounts'] = true;
            }
            $content = ($settings['title'] !== '' ? BlockRewriter::heading($settings['title']) . "\n\n" : '')
                . '<!-- wp:archives ' . wp_json_encode($attributes, JSON_UNESCAPED_SLASHES) . ' /-->';
            $newId = self::addInstance('block', ['content' => $content]);
        }

        $sidebars = self::sidebars();
        $sidebar = $widget['sidebar'];
        // Out of "Inactive widgets", back where it was.
        if ($widget['from'] !== null) {
            $sidebars[$widget['from']] = array_values(array_diff((array) ($sidebars[$widget['from']] ?? []), [$widget['id']]));
        }
        $ids = is_array($sidebars[$sidebar] ?? null) ? $sidebars[$sidebar] : [];
        $position = array_search($widget['id'], $ids, true);
        if ($position === false) {
            array_splice($ids, min($widget['position'], count($ids)), 0, [$newId]);
        } else {
            $ids[$position] = $newId;
        }
        $sidebars[$sidebar] = array_values($ids);
        wp_set_sidebars_widgets($sidebars);

        $context->log->changed(
            $context,
            $this->key(),
            'widget',
            0,
            $widget['id'],
            ['id' => $widget['id'], 'sidebar' => $widget['from'] ?? $sidebar, 'instance' => $widget['instance']],
            ['id' => $newId, 'sidebar' => $sidebar],
            $widget['kind'] === 'calendar' && $settings['theme'] !== '' ? __('Its colour theme is not kept; the calendar takes the theme\'s style.', 'persian-kit') : ''
        );
    }

    /**
     * The widget's settings, from either format.
     *
     * @param array{instance: array<string, mixed>, format: string, kind: string} $widget
     * @return array{title: string, post_type: string, type: string, count: bool, dropdown: bool, theme: string}
     */
    public static function settings(array $widget): array
    {
        $i = $widget['instance'];
        $legacy = $widget['format'] === 'legacy';
        $calendar = $widget['kind'] === 'calendar';

        $title = $legacy ? ($i[$calendar ? 'parsidate_calendar_title' : 'parsidate_archive_title'] ?? '') : ($i['title'] ?? '');
        $type = (string) ($legacy ? ($i['parsidate_archive_type'] ?? 'monthly') : ($i['type'] ?? 'monthly'));

        return [
            'title'     => wp_strip_all_tags((string) $title),
            'post_type' => (string) ($i['post_type'] ?? 'post'),
            'type'      => in_array($type, ['yearly', 'monthly', 'daily', 'weekly', 'postbypost'], true) ? $type : 'monthly',
            'count'     => !empty($legacy ? ($i['parsidate_archive_count'] ?? 0) : ($i['display_count'] ?? 0)),
            'dropdown'  => !empty($legacy ? ($i['parsidate_archive_list'] ?? 0) : ($i['display_select'] ?? 0)),
            'theme'     => (string) ($legacy ? ($i['theme_color'] ?? '') : ($i['theme'] ?? '')),
        ];
    }

    /**
     * Adds an instance of a core widget and returns its widget id.
     *
     * @param array<string, mixed> $instance
     */
    private static function addInstance(string $base, array $instance): string
    {
        $instances = get_option('widget_' . $base, []);
        $instances = is_array($instances) ? $instances : [];
        $numbers = array_filter(array_keys($instances), 'is_int');
        $number = max([1, ...$numbers]) + 1;
        $instances[$number] = $instance;
        $instances['_multiwidget'] = 1;
        update_option('widget_' . $base, $instances);

        return $base . '-' . $number;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sidebars(): array
    {
        $sidebars = get_option('sidebars_widgets', []);

        return is_array($sidebars) ? $sidebars : [];
    }

    /**
     * @param array<string, mixed> $sidebars
     * @return array{0: string, 1: int}|null
     */
    private static function find(array $sidebars, string $id): ?array
    {
        foreach ($sidebars as $sidebar => $ids) {
            if ($sidebar === 'array_version' || !is_array($ids)) {
                continue;
            }
            $position = array_search($id, $ids, true);
            if ($position !== false) {
                return [(string) $sidebar, (int) $position];
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function split(string $id): array
    {
        $dash = strrpos($id, '-');

        return $dash === false ? [$id, 0] : [substr($id, 0, $dash), (int) substr($id, $dash + 1)];
    }

    private static function sidebarName(string $id): string
    {
        global $wp_registered_sidebars;

        return (string) ($wp_registered_sidebars[$id]['name'] ?? $id);
    }
}
