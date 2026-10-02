<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateInputParser;

defined('ABSPATH') || exit;

/**
 * Fixes what people type into Contact Form 7's date, phone and number
 * fields: a typed Jalali date becomes Gregorian Y-m-d, and Persian and
 * Arabic digits become English digits.
 *
 * CF7's own checks read $_POST, after these filters run but not through
 * them, so the fixed value is written back there too. CF7 checks the
 * form's nonce and spam rules itself.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Missing
 */
class Cf7InputNormalizer
{
    /** Field type => the method that fixes it. */
    private const TYPES = [
        'date'   => 'normalizeDate',
        'tel'    => 'normalizeDigits',
        'number' => 'normalizeDigits',
        'range'  => 'normalizeDigits',
    ];

    public function register(): void
    {
        foreach (array_keys(self::TYPES) as $type) {
            add_filter("wpcf7_posted_data_{$type}", [$this, 'normalizePostedValue'], 10, 3);
            add_filter("wpcf7_posted_data_{$type}*", [$this, 'normalizePostedValue'], 10, 3);
        }
    }

    /**
     * @param mixed $value
     * @param mixed $valueOrig
     * @param mixed $tag WPCF7_FormTag
     * @return mixed
     */
    public function normalizePostedValue($value, $valueOrig, $tag)
    {
        if (!is_string($value) || $value === '' || !is_object($tag)) {
            return $value;
        }

        $method = self::TYPES[(string) ($tag->basetype ?? '')] ?? null;
        if ($method === null) {
            return $value;
        }

        $fixed = $this->$method($value);
        $name = (string) ($tag->name ?? '');

        if ($fixed !== $value && $name !== '' && isset($_POST[$name]) && is_string($_POST[$name])) {
            $_POST[$name] = wp_slash($fixed);
        }

        return $fixed;
    }

    public function normalizeDate(string $value): string
    {
        return DateInputParser::normalize($value);
    }

    public function normalizeDigits(string $value): string
    {
        return persian_kit_to_english_digits($value);
    }
}
