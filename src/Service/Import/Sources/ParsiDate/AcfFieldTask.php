<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * Parsi Date's ACF date fields (jalali_datepicker) become ACF's date
 * picker, which Persian Kit shows in Jalali: shown as d/m/Y, returned to
 * templates as Y/m/d. Parsi Date returned 1403-05-12; templates now get
 * 1403/05/12.
 *
 * Fields saved in the database are changed and their group saved again,
 * which also rewrites its local JSON file when it has one. Groups only in
 * local JSON or PHP can't be changed here; each is reported with the change
 * to make.
 */
class AcfFieldTask extends AbstractTask
{
    public const KEY = 'acf_fields';

    /** What the date picker gets. */
    public const SETTINGS = ['type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'Y/m/d', 'first_day' => 6];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('ACF date fields', 'persian-kit');
    }

    public function available(): ?string
    {
        return AcfFields::acfActive() ? null : __('Activate ACF to convert these dates.', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return count(AcfFields::ofType(AcfFields::JALALI));
    }

    public function preview(ImportContext $context, int $limit): array
    {
        return array_map(static fn (array $entry): array => [
            'label'  => (string) ($entry['group']['title'] ?? '') . ' › ' . (string) ($entry['field']['label'] ?? $entry['field']['name'] ?? ''),
            'before' => __('Parsi Date date field', 'persian-kit'),
            'after'  => AcfFields::inDatabase($entry['field']) ? __('ACF date picker', 'persian-kit') : __('Defined in code: to change by hand', 'persian-kit'),
        ], array_slice(AcfFields::ofType(AcfFields::JALALI), 0, $limit));
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $entries = AcfFields::ofType(AcfFields::JALALI);
        $groups = [];

        foreach ($entries as $entry) {
            $field = $entry['field'];
            $group = $entry['group'];
            $id = (int) ($field['ID'] ?? 0);

            $this->guarded($context, 'acf_field', $id, function () use ($context, $field, $group, $id, &$groups): void {
                if (!AcfFields::inDatabase($field)) {
                    $context->log->attention($context, $this->key(), 'acf_field', $id, (string) $field['key'], self::byHand($field, $group));

                    return;
                }

                $old = array_intersect_key($field, self::SETTINGS + ['placeholder' => '']);
                acf_update_field(array_replace($field, self::SETTINGS));
                $context->log->changed(
                    $context,
                    $this->key(),
                    'acf_field',
                    $id,
                    (string) $field['key'],
                    $old,
                    self::SETTINGS,
                    __('Templates now get dates as 1403/05/12; Parsi Date gave 1403-05-12.', 'persian-kit')
                );
                $groups[(string) ($group['key'] ?? '')] = $group;
            });
        }

        foreach ($groups as $group) {
            $this->resaveGroup($context, $group);
        }

        return new TaskBatch(count($entries), null, true);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        $field = function_exists('acf_get_field') ? acf_get_field($row->objectId) : false;
        if (!is_array($field) || !is_array($row->oldValue) || !is_array($row->newValue)) {
            return ImportLog::KEPT;
        }

        foreach ($row->newValue as $key => $value) {
            if (($field[$key] ?? null) != $value) {
                return ImportLog::KEPT;
            }
        }

        acf_update_field(array_replace($field, $row->oldValue));

        return ImportLog::RESTORED;
    }

    /**
     * Saves the group again, so ACF writes its local JSON file too.
     *
     * @param array<string, mixed> $group
     */
    private function resaveGroup(ImportContext $context, array $group): void
    {
        if (empty($group['ID']) || !function_exists('acf_get_field_group')) {
            return;
        }

        $saved = acf_get_field_group((int) $group['ID']);
        if (!is_array($saved)) {
            return;
        }
        acf_update_field_group($saved);

        $file = (string) ($group['local_file'] ?? '');
        if ($file !== '') {
            $context->log->unchanged(
                $context,
                $this->key(),
                'acf_group',
                (int) $group['ID'],
                (string) $group['key'],
                /* translators: %s: file path. */
                sprintf(__('Its local JSON file was rewritten: %s', 'persian-kit'), self::relative($file))
            );
        }
    }

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $group
     */
    private static function byHand(array $field, array $group): string
    {
        $where = (string) ($group['local_file'] ?? '');
        $where = $where !== '' ? self::relative($where) : __('PHP code (acf_add_local_field_group)', 'persian-kit');

        return sprintf(
            /* translators: 1: field label, 2: field group title, 3: file or "PHP code". */
            __('"%1$s" in "%2$s" is defined in %3$s. Its values were converted; change the field there to \'type\' => \'date_picker\', \'display_format\' => \'d/m/Y\', \'return_format\' => \'Y/m/d\'.', 'persian-kit'),
            (string) ($field['label'] ?? $field['name'] ?? $field['key']),
            (string) ($group['title'] ?? $group['key'] ?? ''),
            $where
        );
    }

    private static function relative(string $path): string
    {
        return str_starts_with($path, ABSPATH) ? substr($path, strlen(ABSPATH)) : $path;
    }
}
