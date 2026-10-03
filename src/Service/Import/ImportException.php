<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * Why an import request can't go ahead, with the HTTP status REST answers.
 */
class ImportException extends \RuntimeException
{
    public const LOCKED = 'persian_kit_import_locked';
    public const NOT_READY = 'persian_kit_import_not_ready';
    public const NO_JOB = 'persian_kit_import_no_job';
    public const OTHER_JOB = 'persian_kit_import_other_job';
    public const UNKNOWN_SOURCE = 'persian_kit_import_unknown_source';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
