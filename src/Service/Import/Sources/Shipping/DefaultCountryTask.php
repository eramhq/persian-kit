<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Service\Import\AbstractTask;
use PersianKit\Service\Import\ImportContext;
use PersianKit\Service\Import\ImportLog;
use PersianKit\Service\Import\Iran\IranAddressResolver;
use PersianKit\Service\Import\Iran\IranProvinces;
use PersianKit\Service\Import\LogRow;
use PersianKit\Service\Import\TaskBatch;

defined('ABSPATH') || exit;

/**
 * The store's address: woocommerce_default_country IR:123 becomes IR:THR.
 */
class DefaultCountryTask extends AbstractTask
{
    public const KEY = 'default_country';
    private const OPTION = 'woocommerce_default_country';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Store address', 'persian-kit');
    }

    public function count(ImportContext $context): int
    {
        return $this->state() !== null ? 1 : 0;
    }

    public function preview(ImportContext $context, int $limit): array
    {
        $state = $this->state();
        $code = $state === null ? null : IranAddressResolver::forSite($context->snapshot)->stateCode($state);

        return $state === null ? [] : [[
            'label'  => __('Store address', 'persian-kit'),
            'before' => $state,
            'after'  => $code === null ? __('Unknown province', 'persian-kit') : IranProvinces::name($code),
        ]];
    }

    public function run(ImportContext $context, mixed $cursor, int $limit): TaskBatch
    {
        $state = $this->state();
        if ($state !== null) {
            $old = (string) get_option(self::OPTION);
            $code = IranAddressResolver::forSite($context->snapshot)->stateCode($state);

            if ($code === null) {
                /* translators: %s: province as saved. */
                $context->log->attention($context, $this->key(), 'option', 0, self::OPTION, sprintf(__('Unknown province: %s', 'persian-kit'), $state), $old);
            } else {
                update_option(self::OPTION, 'IR:' . $code);
                $context->log->changed($context, $this->key(), 'option', 0, self::OPTION, $old, 'IR:' . $code);
            }
        }

        return new TaskBatch($state === null ? 0 : 1, null, true);
    }

    public function undo(ImportContext $context, LogRow $row): string
    {
        if (get_option(self::OPTION) !== $row->newValue) {
            return ImportLog::KEPT;
        }

        update_option(self::OPTION, (string) $row->oldValue);

        return ImportLog::RESTORED;
    }

    /**
     * The province part, while it is not a WooCommerce code.
     */
    private function state(): ?string
    {
        $value = (string) get_option(self::OPTION);
        if (!str_starts_with($value, 'IR:')) {
            return null;
        }

        $state = substr($value, 3);

        return $state !== '' && $state !== '*' && !IranProvinces::isCode($state) ? $state : null;
    }
}
