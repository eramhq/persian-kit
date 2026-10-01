<?php

namespace PersianKit\Tests\Unit\Support;

/**
 * Runs code with PCRE limits low enough that abzar's HTML segmenter fails,
 * so the FormatException paths can be exercised.
 */
trait FailsPcre
{
    /** An unterminated <script> block forces the segmenter to backtrack. */
    protected static function unsegmentableHtml(): string
    {
        return '<script>' . str_repeat('ك 1 ', 200);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected static function withFailingPcre(callable $callback): mixed
    {
        $limit = ini_get('pcre.backtrack_limit');
        $jit = ini_get('pcre.jit');
        ini_set('pcre.backtrack_limit', '10');
        ini_set('pcre.jit', '0');

        try {
            return $callback();
        } finally {
            ini_set('pcre.backtrack_limit', (string) $limit);
            ini_set('pcre.jit', (string) $jit);
        }
    }
}
