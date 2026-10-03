<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\ChecklistItem;

defined('ABSPATH') || exit;

/**
 * The "Before you deactivate" items for code that calls a source plugin.
 * A call outside a function_exists() check stops the page with a fatal
 * error once the plugin is inactive, so it keeps Deactivate closed until
 * it is handled.
 */
final class ThemeCalls
{
    /**
     * @param list<string>         $functions The plugin's functions.
     * @param array<string, mixed> $snapshot  With 'rescan' to scan again.
     * @return list<ChecklistItem>
     */
    public static function items(string $plugin, array $functions, array $snapshot, bool $withSnippet): array
    {
        $scan = (new ThemeScanner())->calls($functions, !empty($snapshot['rescan']));
        $items = [];

        if ($scan['calls'] !== []) {
            $unguarded = array_filter($scan['calls'], static fn (array $call): bool => !$call['guarded']);
            $called = array_values(array_unique(array_column($scan['calls'], 'function')));
            $snippet = $withSnippet ? ThemeSnippet::build($called) : '';

            $items[] = new ChecklistItem(
                'theme_calls',
                sprintf(
                    /* translators: %s: plugin name. */
                    __('Your theme or must-use plugins call functions of %s', 'persian-kit'),
                    $plugin
                ),
                $unguarded !== []
                    ? __('These calls stop the page with an error once it is inactive. Copy the code below into your theme\'s functions.php, or change the calls.', 'persian-kit')
                    : __('These calls are inside a function_exists() check: once it is inactive they print nothing.', 'persian-kit'),
                array_map(static fn (array $call): array => [
                    'label'  => $call['file'] . ':' . $call['line'] . ' ' . $call['function'] . '()',
                    'detail' => $call['guarded'] ? __('prints nothing', 'persian-kit') : __('stops the page', 'persian-kit'),
                ], $scan['calls']),
                $unguarded !== [],
                true,
                [
                    'snippet'           => $snippet,
                    'snippet_help'      => __('Defines each function with Persian Kit\'s, only while nothing else defines it.', 'persian-kit'),
                    'acknowledge_label' => __('I have added the code or changed these calls', 'persian-kit'),
                    'truncated'         => $scan['truncated'],
                ]
            );
        }

        return $items;
    }
}
