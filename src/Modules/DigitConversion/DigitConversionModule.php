<?php

namespace PersianKit\Modules\DigitConversion;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Dependencies\Eram\Abzar\Exception\AbzarException;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;

defined('ABSPATH') || exit;

class DigitConversionModule extends AbstractModule
{
    public static function key(): string
    {
        return 'digit_conversion';
    }

    public static function label(): string
    {
        return __('Digit Conversion', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Converts English/Arabic digits to Persian in content', 'persian-kit');
    }

    public static function defaults(): array
    {
        return ['enabled' => true];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
        $this->registerFilter('the_content', [self::class, 'convertContent']);
        $this->registerFilter('the_title', [DigitConverter::class, 'toPersian']);
        $this->registerFilter('the_excerpt', [self::class, 'convertContent']);
        $this->registerFilter('get_the_excerpt', [self::class, 'convertContent']);
        $this->registerFilter('comment_text', [self::class, 'convertContent']);
        $this->registerFilter('widget_text', [self::class, 'convertContent']);
        $this->registerFilter('widget_text_content', [self::class, 'convertContent']);
        $this->registerFilter('human_time_diff', [DigitConverter::class, 'toPersian']);

        $this->registerFilter('get_the_terms', function ($terms) {
            if (!is_array($terms)) {
                return $terms;
            }

            foreach ($terms as $term) {
                if (isset($term->name)) {
                    $term->name = DigitConverter::toPersian($term->name);
                }
            }

            return $terms;
        });
    }

    /**
     * Convert digits in HTML, leaving the content untouched when abzar cannot
     * segment it (PCRE limits on very large or malformed markup).
     */
    public static function convertContent(string $html): string
    {
        try {
            return DigitConverter::convertContent($html);
        } catch (AbzarException) {
            return $html;
        }
    }

    private function registerFilter(string $hook, callable $callback): void
    {
        if (apply_filters('persian_kit_digit_conversion', true, $hook)) {
            add_filter($hook, $callback, 99);
        }
    }
}
