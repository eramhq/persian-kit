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
        return __('Persian digits', 'persian-kit');
    }

    public static function description(): string
    {
        return __('Show ۱۲۳ instead of 123 in your site\'s content.', 'persian-kit');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return ['enabled' => false, 'dates' => true, 'numbers' => true, 'prices' => true];
    }

    public function settingsView(): ?string
    {
        return 'admin/partials/digit-conversion-settings';
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeSettings(array $values): array
    {
        return [
            'enabled' => !empty($values['enabled']),
            'dates'   => !empty($values['dates']),
            'numbers' => !empty($values['numbers']),
            'prices'  => !empty($values['prices']),
        ];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
        if (!$this->isFrontendRequest()) {
            return;
        }

        $this->registerFilter('the_content', [$this, 'filterContent']);
        $this->registerFilter('the_title', [$this, 'filterText']);
        $this->registerFilter('get_the_excerpt', [$this, 'filterContent']);
        $this->registerFilter('comment_text', [$this, 'filterContent']);
        $this->registerFilter('widget_text', [$this, 'filterContent']);
        $this->registerFilter('widget_text_content', [$this, 'filterContent']);
        $this->registerFilter('human_time_diff', [$this, 'filterText']);
        $this->registerFilter('get_the_terms', [$this, 'filterTerms']);

        if ($this->setting('dates')) {
            $this->registerFilter('persian_kit_date_display', [$this, 'filterText']);
        }

        if ($this->setting('numbers')) {
            $this->registerFilter('number_format_i18n', [$this, 'filterText']);
        }

        if ($this->setting('prices')) {
            $this->registerFilter('formatted_woocommerce_price', [$this, 'filterText']);

            if (apply_filters('persian_kit_digit_conversion', true, 'woocommerce_block_prices')) {
                add_action('wp_enqueue_scripts', [$this, 'enqueueBlockPriceScript']);
            }
        }
    }

    /**
     * The cart and checkout blocks format prices in the browser, so a script
     * converts their digits. Loaded on the cart and checkout pages only.
     */
    public function enqueueBlockPriceScript(): void
    {
        if (!function_exists('is_cart') || !(is_cart() || is_checkout())) {
            return;
        }

        if (!has_block('woocommerce/cart') && !has_block('woocommerce/checkout')) {
            return;
        }

        wp_enqueue_script(
            'persian-kit-woocommerce-block-prices',
            PERSIAN_KIT_URL . 'public/js/woocommerce-block-prices.js',
            [],
            PERSIAN_KIT_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );
    }

    public function filterContent(?string $html): ?string
    {
        return $html === null || $html === '' || !$this->shouldConvertNow() ? $html : self::convertContent($html);
    }

    public function filterText(?string $text): ?string
    {
        return $text === null || $text === '' || !$this->shouldConvertNow() ? $text : DigitConverter::toPersian($text);
    }

    /**
     * Renames copies of the terms: the WP_Term objects themselves live in the
     * object cache and are shared with every other caller.
     *
     * @param mixed $terms
     * @return mixed
     */
    public function filterTerms($terms)
    {
        if (!is_array($terms) || !$this->shouldConvertNow()) {
            return $terms;
        }

        return array_map(static function ($term) {
            if (!is_object($term) || !isset($term->name) || !is_string($term->name)) {
                return $term;
            }

            $term = clone $term;
            $term->name = DigitConverter::toPersian($term->name);

            return $term;
        }, $terms);
    }

    /**
     * Convert digits in HTML, leaving the content untouched when abzar cannot
     * segment it (PCRE limits on very large or malformed markup). Keyboard
     * input and program output (<kbd>, <samp>) keep their digits, as code does.
     */
    public static function convertContent(string $html): string
    {
        try {
            if (stripos($html, '<kbd') === false && stripos($html, '<samp') === false) {
                return DigitConverter::convertContent($html);
            }

            $parts = preg_split('/(<kbd[\s>][\s\S]*?<\/kbd\s*>|<samp[\s>][\s\S]*?<\/samp\s*>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                return $html;
            }

            foreach ($parts as $index => $part) {
                if ($index % 2 === 0 && $part !== '') {
                    $parts[$index] = DigitConverter::convertContent($part);
                }
            }

            return implode('', $parts);
        } catch (AbzarException) {
            return $html;
        }
    }

    /**
     * Admin screens keep Latin digits; admin-ajax counts as front end only when
     * the request came from outside wp-admin (infinite scroll, load-more).
     */
    private function isFrontendRequest(): bool
    {
        if (!is_admin()) {
            return true;
        }

        if (!wp_doing_ajax()) {
            return false;
        }

        $referer = (string) wp_get_raw_referer();

        return $referer !== '' && !str_starts_with($referer, admin_url());
    }

    /**
     * REST responses, feeds and outgoing mail are read by machines or mail
     * clients, so their digits stay as stored.
     */
    private function shouldConvertNow(): bool
    {
        if (wp_is_serving_rest_request()) {
            return false;
        }

        if (isset($GLOBALS['wp_query']) && is_feed()) {
            return false;
        }

        return !doing_filter('wp_mail');
    }

    private function registerFilter(string $hook, callable $callback): void
    {
        if (apply_filters('persian_kit_digit_conversion', true, $hook)) {
            add_filter($hook, $callback, 99);
        }
    }
}
