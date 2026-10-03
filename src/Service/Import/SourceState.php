<?php

namespace PersianKit\Service\Import;

defined('ABSPATH') || exit;

/**
 * Where a source stands, for its row on the Tools tab.
 */
enum SourceState: string
{
    case Active = 'active';
    case InactiveWithData = 'inactive';
    case PartlyImported = 'partly';
    case Imported = 'imported';
    /** Never used on this site; not listed. */
    case NoData = 'none';
}
