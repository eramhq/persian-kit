<?php

namespace PersianKit\Modules\DigitConversion;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Dependencies\Eram\Abzar\Exception\AbzarException;
use PersianKit\Abstracts\AbstractModule;
use PersianKit\Container\ServiceContainer;
use PersianKit\Service\Language\ContentLanguage;

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
        return ['enabled' => false, 'dates' => true, 'numbers' => true, 'prices' => true, 'emails' => false];
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
            'emails'  => !empty($values['emails']),
        ];
    }

    public function register(ServiceContainer $container): void
    {
    }

    public function boot(ServiceContainer $container): void
    {
        // Emails are sent from the admin, REST, cron and checkout alike.
        if ($this->wooCommerceLoaded()) {
            WooCommerceEmailDigits::trackRendering();

            if ($this->setting('emails') && $this->allows('woocommerce_emails')) {
                (new WooCommerceEmailDigits($this->allows('woocommerce_email_order_number')))->register();
            }
        }

        // Admin screens keep English digits.
        if (ContentLanguage::isAdminRequest()) {
            return;
        }

        // Everything that may hold HTML or character references (wptexturize
        // makes &#8211; and &#8217; in titles) goes through filterContent.
        foreach ([
            'the_content', 'the_title', 'get_the_excerpt', 'comment_text', 'widget_text', 'widget_text_content',
            'single_post_title', 'single_cat_title', 'single_tag_title', 'single_term_title',
            'get_the_archive_description', 'term_description',
            'widget_title', 'widget_block_content', 'widget_custom_html_content',
            'list_cats', 'get_comment_excerpt', 'woocommerce_short_description',
            'render_block_core/navigation', 'render_block_core/page-list', 'render_block_core/term-name',
        ] as $hook) {
            $this->registerFilter($hook, [$this, 'filterContent']);
        }

        $this->registerFilter('get_the_archive_title', [$this, 'filterArchiveTitle']);
        $this->registerFilter('wp_generate_tag_cloud_data', [$this, 'filterTagCloudData']);
        $this->registerFilter('human_time_diff', [$this, 'filterText']);
        $this->registerFilter('get_the_terms', [$this, 'filterTerms']);

        // Late, after SEO plugins write the title (Yoast at 15, Rank Math at 30).
        foreach (['pre_get_document_title', 'document_title', 'wp_title'] as $hook) {
            $this->registerFilter($hook, [$this, 'filterDocumentTitle'], 9999);
        }

        if ($this->setting('dates')) {
            $this->registerFilter('persian_kit_date_display', [$this, 'filterText']);
        }

        if ($this->setting('numbers')) {
            $this->registerFilter('number_format_i18n', [$this, 'filterText']);
            $this->registerFilter('get_archives_link', [$this, 'filterArchivesLink'], 99, 6);
            $this->registerFilter('render_block_core/term-count', [$this, 'filterContent']);
            $this->registerFilter('render_block_core/query-total', [$this, 'filterContent']);
        }

        if ($this->setting('prices')) {
            $this->registerFilter('formatted_woocommerce_price', [$this, 'filterText']);

            if ($this->allows('woocommerce_block_prices')) {
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
        if (!function_exists('is_cart') || !(is_cart() || is_checkout()) || !ContentLanguage::displaysPersian()) {
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
     * The browser tab's title, from core, Yoast SEO or Rank Math, also inside
     * wp_head. The site name keeps the digits it was typed with, as it does
     * everywhere else on the page. An empty pre_get_document_title is left
     * empty, so core still builds the title.
     */
    public function filterDocumentTitle(?string $title): ?string
    {
        if ($title === null || $title === '' || !$this->shouldConvertNow(true)) {
            return $title;
        }

        $names = array_unique(array_filter([
            (string) get_bloginfo('name'),
            (string) get_bloginfo('name', 'display'),
        ], static fn (string $name): bool => $name !== '' && preg_match('/[0-9]/', $name) === 1));

        if ($names === []) {
            return self::convertContent($title);
        }

        // Longest first, so a name that contains the other is split whole.
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '/(' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $names)) . ')/';
        $parts = preg_split($pattern, $title, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $title;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 && $part !== '') {
                $parts[$index] = self::convertContent($part);
            }
        }

        return implode('', $parts);
    }

    /**
     * Date archive titles belong to DateArchiveFilter and the dates option.
     */
    public function filterArchiveTitle(?string $title): ?string
    {
        return is_date() ? $title : $this->filterContent($title);
    }

    /**
     * Tag names, and the counts when the numbers option is on. Links,
     * real counts and aria labels are left alone.
     *
     * @param mixed $tags
     * @return mixed
     */
    public function filterTagCloudData($tags)
    {
        if (!is_array($tags) || !$this->shouldConvertNow()) {
            return $tags;
        }

        $counts = (bool) $this->setting('numbers');

        foreach ($tags as $index => $tag) {
            if (!is_array($tag)) {
                continue;
            }

            if (isset($tag['name']) && is_string($tag['name'])) {
                $tags[$index]['name'] = self::convertContent($tag['name']);
            }

            if ($counts && isset($tag['show_count']) && is_string($tag['show_count']) && $tag['show_count'] !== '') {
                $tags[$index]['show_count'] = self::convertContent($tag['show_count']);
            }
        }

        return $tags;
    }

    /**
     * The post count after an archive link (&nbsp;(N)). The label itself is
     * left alone: a Jalali label is already converted, a Gregorian one keeps
     * its digits, like every Gregorian date.
     */
    public function filterArchivesLink(?string $link, mixed $url = '', mixed $text = '', mixed $format = '', mixed $before = '', mixed $after = ''): ?string
    {
        if ($link === null || !is_string($after) || $after === '' || !preg_match('/[0-9]/', $after) || !$this->shouldConvertNow()) {
            return $link;
        }

        $position = strrpos($link, $after);
        if ($position === false) {
            return $link;
        }

        return substr_replace($link, self::convertContent($after), $position, strlen($after));
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
        if (strpbrk($html, '<&') === false) {
            return DigitConverter::toPersian($html);
        }

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
     * REST responses, feeds and outgoing mail are read by machines or mail
     * clients, so their digits stay as stored. WooCommerce emails follow
     * their own option (WooCommerceEmailDigits), in the body and in the
     * subject and heading. On multilingual sites, only pages in Persian
     * are converted.
     *
     * Tags printed in wp_head (Open Graph, Twitter cards, JSON-LD from Yoast
     * SEO and Rank Math) keep their digits; only the document title is
     * converted there. Block themes render the page before wp_head, so their
     * visible content still converts.
     *
     * Nothing converts while a post is saved: plugins store what they read
     * then, such as the breadcrumb title in Yoast SEO's indexables.
     */
    private function shouldConvertNow(bool $documentTitle = false): bool
    {
        if (doing_action('save_post') || doing_action('wp_insert_post')) {
            return false;
        }

        if (!$documentTitle && doing_action('wp_head')) {
            return false;
        }

        if (wp_is_serving_rest_request()) {
            return false;
        }

        if (isset($GLOBALS['wp_query']) && is_feed()) {
            return false;
        }

        if (WooCommerceEmailDigits::isRendering() || doing_filter('woocommerce_email_format_string')) {
            return false;
        }

        return !doing_filter('wp_mail') && ContentLanguage::displaysPersian();
    }

    protected function wooCommerceLoaded(): bool
    {
        return function_exists('WC');
    }

    private function registerFilter(string $hook, callable $callback, int $priority = 99, int $args = 1): void
    {
        if ($this->allows($hook)) {
            add_filter($hook, $callback, $priority, $args);
        }
    }

    private function allows(string $hook): bool
    {
        return (bool) apply_filters('persian_kit_digit_conversion', true, $hook);
    }
}
