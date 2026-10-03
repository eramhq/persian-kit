<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Puts what can't be converted automatically on the report: code calling
 * the plugin's functions, Parsi Date's blocks in a block theme's files and
 * its widgets in Elementor pages. Changes nothing.
 */
class ThemeScanTask extends AbstractTask
{
    public const KEY = 'theme_scan';

    /** @var list<string> */
    private array $functions;
    private bool $parsiDate;

    /**
     * @param list<string> $functions
     */
    public function __construct(array $functions, bool $parsiDate)
    {
        $this->functions = $functions;
        $this->parsiDate = $parsiDate;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Theme code and templates', 'persian-kit');
    }

    public function requiresInactive(): bool
    {
        return false;
    }

    public function count(ImportContext $context): int
    {
        return count($this->findings());
    }

    public function preview(ImportContext $context, int $limit): array
    {
        return array_map(static fn (array $finding): array => ['label' => $finding['label'], 'before' => $finding['reason'], 'after' => __('To change by hand', 'persian-kit')], array_slice($this->findings(), 0, $limit));
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $findings = $this->findings();
        foreach ($findings as $finding) {
            $context->log->attention($context, $this->key(), $finding['type'], $finding['id'], $finding['label'], $finding['reason']);
        }

        return new TaskBatch(count($findings), null, true);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        return ImportLog::KEPT;
    }

    /**
     * @return list<array{type: string, id: int, label: string, reason: string}>
     */
    private function findings(): array
    {
        $scanner = new ThemeScanner();
        $findings = [];

        foreach ($scanner->calls($this->functions)['calls'] as $call) {
            $findings[] = [
                'type'   => 'file',
                'id'     => 0,
                'label'  => $call['file'] . ':' . $call['line'],
                'reason' => sprintf(
                    /* translators: %s: function name. */
                    $call['guarded'] ? __('%s() prints nothing now. Use the code from "Before you deactivate", or change the call.', 'persian-kit') : __('%s() stops the page with an error. Use the code from "Before you deactivate", or change the call.', 'persian-kit'),
                    $call['function']
                ),
            ];
        }

        if ($this->parsiDate) {
            foreach ($scanner->blockTemplates() as $file) {
                $findings[] = [
                    'type'   => 'file',
                    'id'     => 0,
                    'label'  => $file,
                    'reason' => __('Parsi Date blocks in a theme file can\'t be rewritten. Open the template in the Site Editor, replace them with the Archives or Calendar block and save.', 'persian-kit'),
                ];
            }
            foreach (ThemeScanner::elementorPages() as $postId) {
                $findings[] = [
                    'type'   => 'post',
                    'id'     => $postId,
                    'label'  => get_the_title($postId),
                    'reason' => __('Built with Elementor and holds a Parsi Date widget. Replace it with WordPress\'s Archives or Calendar widget in Elementor.', 'persian-kit'),
                ];
            }
        }

        return $findings;
    }
}
