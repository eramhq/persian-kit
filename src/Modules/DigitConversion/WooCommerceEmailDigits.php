<?php

namespace PersianKit\Modules\DigitConversion;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\WooCommerce\EmailEditorTags;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Persian digits in WooCommerce emails, in the values people read: order
 * numbers, prices, quantities and dates, in the body and in the subject and
 * heading. What people copy or machines read keeps English digits: phone
 * numbers, postcodes, links, coupon codes and the order's structured data
 * for Gmail. The body is never converted as a whole.
 *
 * Every email body (HTML, plain text and the multipart plain part) is built
 * from template parts named emails/…, so the converters work only while one
 * renders, and only for emails in a language that reads Jalali dates, such
 * as Persian or Pashto (isPersianEmail()). In the block email editor, the
 * personalization tags (order number, date, totals) are filled in after the
 * template parts, so their callbacks count as rendering too.
 */
class WooCommerceEmailDigits
{
    private const TEMPLATE_PREFIX = 'emails/';

    /** Placeholders in subjects and headings whose values are converted. */
    private const ORDER_NUMBER_PLACEHOLDER = '{order_number}';
    private const ORDER_DATE_PLACEHOLDER = '{order_date}';

    private const PERSONALIZATION_TAGS_FILTER = 'woocommerce_email_editor_register_personalization_tags';

    /** Block email editor tags whose values are amounts of money. */
    private const MONEY_TAGS = [
        '[woocommerce/order-subtotal]',
        '[woocommerce/order-tax]',
        '[woocommerce/order-discount]',
        '[woocommerce/order-shipping]',
        '[woocommerce/order-total]',
    ];

    private static int $depth = 0;

    private bool $inlining = false;

    public function __construct(private bool $orderNumbers = true)
    {
    }

    /**
     * Tracks email rendering, also while emails are not converted, so the
     * site-wide digit filters can leave emails alone.
     */
    public static function trackRendering(): void
    {
        add_action('woocommerce_before_template_part', [self::class, 'enterTemplate']);
        add_action('woocommerce_after_template_part', [self::class, 'leaveTemplate']);
        add_filter(self::PERSONALIZATION_TAGS_FILTER, [self::class, 'scopeOrderTags'], 30);
    }

    /**
     * The block email editor's order tags run as part of the email, so the
     * order number and date filters reach them, and the site-wide ones don't.
     */
    public static function scopeOrderTags(mixed $registry): mixed
    {
        return EmailEditorTags::wrap(
            $registry,
            [EmailEditorTags::class, 'isOrderTag'],
            static fn (callable $original, mixed $context, mixed $args): mixed => self::during(
                static fn (): mixed => $original($context, $args)
            )
        );
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function during(callable $fn): mixed
    {
        self::$depth++;

        try {
            return $fn();
        } finally {
            self::$depth = max(0, self::$depth - 1);
        }
    }

    public static function isRendering(): bool
    {
        return self::$depth > 0;
    }

    public static function enterTemplate(mixed $templateName = ''): void
    {
        if (is_string($templateName) && str_starts_with($templateName, self::TEMPLATE_PREFIX)) {
            self::$depth++;
        }
    }

    public static function leaveTemplate(mixed $templateName = ''): void
    {
        if (is_string($templateName) && str_starts_with($templateName, self::TEMPLATE_PREFIX)) {
            self::$depth = max(0, self::$depth - 1);
        }
    }

    public function register(): void
    {
        // Inside wc_price(), before WooCommerce wraps the number in HTML.
        add_filter('formatted_woocommerce_price', [$this, 'filterText'], 99);
        add_filter('woocommerce_email_order_item_quantity', [$this, 'filterQuantity'], 99);
        add_filter('date_i18n', [$this, 'filterDate'], 99, 2);
        add_filter('woocommerce_email_format_string', [$this, 'filterFormatString'], 20, 2);

        // Last, after plugins that make their own order numbers.
        if ($this->orderNumbers) {
            add_filter('woocommerce_order_number', [$this, 'filterText'], PHP_INT_MAX);
        }

        // Links and the order's structured data keep English digits, whichever
        // plugin converted them.
        add_filter('clean_url', [$this, 'filterUrl'], PHP_INT_MAX);
        add_filter('woocommerce_structured_data_order', [$this, 'filterStructuredData'], PHP_INT_MAX);

        // After the order tags are scoped (priority 30).
        add_filter(self::PERSONALIZATION_TAGS_FILTER, [$this, 'filterMoneyTags'], 31);
        add_filter('woocommerce_mail_style_inline_callback', [$this, 'filterStyleInlineCallback'], PHP_INT_MAX, 3);
        add_filter('woocommerce_mail_content', [$this, 'filterMailContent'], PHP_INT_MAX);
    }

    /**
     * The block email editor's money tags: the subtotal, tax and total are raw
     * numbers ("220000.00"), which keep their format and only change digits.
     * The discount and shipping are wc_price() HTML, whose markup is kept.
     */
    public function filterMoneyTags(mixed $registry): mixed
    {
        return EmailEditorTags::wrap(
            $registry,
            static fn (string $token): bool => in_array($token, self::MONEY_TAGS, true),
            static function (callable $original, mixed $context, mixed $args): mixed {
                $value = $original($context, $args);

                return is_string($value) && self::isPersianEmail() ? DigitConversionModule::convertContent($value) : $value;
            }
        );
    }

    /**
     * Links built from a block email editor tag get the tag's value as their
     * href after the email has rendered. WooCommerce's style inliner then
     * percent-encodes their Persian digits, which can't be told apart from a
     * permalink's, so links go back to English digits before it runs. The
     * default inliner is private, so the email inlines again, and this filter
     * then keeps the callback it gets.
     */
    public function filterStyleInlineCallback(mixed $callback, mixed $content = null, mixed $email = null): mixed
    {
        if ($this->inlining || !is_object($email) || !method_exists($email, 'style_inline')) {
            return $callback;
        }

        return function (mixed $content) use ($email): mixed {
            $this->inlining = true;

            try {
                return $email->style_inline($this->filterMailContent($content));
            } finally {
                $this->inlining = false;
            }
        };
    }

    /**
     * Links keep English digits: before the style inliner, and in the
     * finished email for plugins that convert the whole message. Only href
     * attributes change: text and other attributes keep their digits.
     */
    public function filterMailContent(mixed $content): mixed
    {
        if (
            !is_string($content)
            || stripos($content, 'href') === false
            || !preg_match('/[\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $content)
            || !class_exists(\WP_HTML_Tag_Processor::class)
        ) {
            return $content;
        }

        $processor = new \WP_HTML_Tag_Processor($content);
        while ($processor->next_tag()) {
            $href = $processor->get_attribute('href');
            if (!is_string($href)) {
                continue;
            }

            $english = DigitConverter::toEnglish($href);
            if ($english !== $href) {
                $processor->set_attribute('href', $english);
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * Prices and order numbers. Order numbers are the order's ID (an int)
     * unless a plugin makes its own.
     */
    public function filterText(mixed $text): mixed
    {
        if (!(is_string($text) || is_int($text)) || !self::converts()) {
            return $text;
        }

        return DigitConverter::toPersian((string) $text);
    }

    /**
     * An int, or HTML for refunded items: <del>2</del> <ins>1</ins>.
     */
    public function filterQuantity(mixed $quantity): mixed
    {
        if (!self::converts()) {
            return $quantity;
        }

        if (is_int($quantity) || is_float($quantity)) {
            return DigitConverter::toPersian((string) $quantity);
        }

        return is_string($quantity) ? DigitConversionModule::convertContent($quantity) : $quantity;
    }

    /**
     * Jalali or Gregorian, as the date filters left it; formats machines read
     * keep English digits.
     */
    public function filterDate(mixed $date, mixed $format = ''): mixed
    {
        if (!is_string($date) || !self::converts() || DateDisplayGuard::shouldBypass((string) $format)) {
            return $date;
        }

        return DigitConverter::toPersian($date);
    }

    /**
     * Subjects and headings are filled in outside the email's template parts,
     * so the order number and date placeholders are converted here: only
     * their values, not the site title or the text around them.
     */
    public function filterFormatString(mixed $string, mixed $email = null): mixed
    {
        if (!is_string($string) || $string === '' || !self::isPersianEmail()) {
            return $string;
        }

        $placeholders = is_object($email) && isset($email->placeholders) && is_array($email->placeholders)
            ? $email->placeholders
            : [];

        $keys = $this->orderNumbers
            ? [self::ORDER_NUMBER_PLACEHOLDER, self::ORDER_DATE_PLACEHOLDER]
            : [self::ORDER_DATE_PLACEHOLDER];

        foreach ($keys as $key) {
            $value = $placeholders[$key] ?? null;
            if (!(is_string($value) || is_int($value))) {
                continue;
            }

            $value = (string) $value;
            if (!preg_match('/[0-9]/', $value)) {
                continue;
            }

            // Whole values only: order 12 leaves the 12 in "2012" alone.
            $string = (string) preg_replace_callback(
                '/(?<![0-9])' . preg_quote($value, '/') . '(?![0-9])/u',
                static fn (): string => DigitConverter::toPersian($value),
                $string
            );
        }

        return $string;
    }

    /**
     * esc_url() output: a link built from an order number or a price, by
     * WooCommerce or another plugin, keeps working.
     */
    public function filterUrl(mixed $url): mixed
    {
        if (!is_string($url) || !self::isRendering()) {
            return $url;
        }

        return DigitConverter::toEnglish($url);
    }

    /**
     * The order's structured data in HTML emails reuses the order number and
     * quantity filters; Gmail reads it, so its digits go back to English and
     * the quantity back to a number.
     *
     * @param mixed $markup
     * @return mixed
     */
    public function filterStructuredData($markup)
    {
        return is_array($markup) ? self::englishDigits($markup) : $markup;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function englishDigits(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::englishDigits($value);
            } elseif (is_string($value)) {
                $data[$key] = DigitConverter::toEnglish($value);
            }
        }

        if (isset($data['eligibleQuantity']['value']) && is_string($data['eligibleQuantity']['value']) && ctype_digit($data['eligibleQuantity']['value'])) {
            $data['eligibleQuantity']['value'] = (int) $data['eligibleQuantity']['value'];
        }

        return $data;
    }

    private static function converts(): bool
    {
        return self::isRendering() && self::isPersianEmail();
    }

    /**
     * The email's language. WooCommerce switches to the site's locale while
     * it builds a customer email (switch_to_locale()), Polylang for
     * WooCommerce to the customer's, and WooCommerce Multilingual switches
     * WPML's language (ContentLanguage follows each), so an English email
     * keeps English digits, and a Pashto one gets Persian digits.
     */
    private static function isPersianEmail(): bool
    {
        return ContentLanguage::readsJalali(ContentLanguage::currentLocale());
    }
}
