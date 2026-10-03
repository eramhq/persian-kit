<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * A source whose undo has a side effect to confirm first.
 */
interface HasUndoWarning
{
    /** Empty when there is nothing to warn about. */
    public function undoWarning(): string;
}
