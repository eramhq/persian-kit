<?php

namespace PersianKit\Modules\DigitConversion;

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
        $this->registerFilter('the_content', [DigitConverter::class, 'convertContent']);
        $this->registerFilter('the_title', [DigitConverter::class, 'toPersian']);
        $this->registerFilter('the_excerpt', [DigitConverter::class, 'convertContent']);
        $this->registerFilter('get_the_excerpt', [DigitConverter::class, 'convertContent']);
        $this->registerFilter('comment_text', [DigitConverter::class, 'convertContent']);
        $this->registerFilter('widget_text', [DigitConverter::class, 'convertContent']);
        $this->registerFilter('widget_text_content', [DigitConverter::class, 'convertContent']);
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

    private function registerFilter(string $hook, callable $callback): void
    {
        if (apply_filters('persian_kit_digit_conversion', true, $hook)) {
            add_filter($hook, $callback, 99);
        }
    }
}
