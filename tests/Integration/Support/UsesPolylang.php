<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Service\Language\ContentLanguage;

/**
 * A Polylang site with Persian (fa_IR, the default), English and Arabic.
 * Polylang reads the language from the address once per process, so the
 * tests set the current language directly.
 *
 * Polylang loads only in the multilingual run: composer test:integration:polylang.
 */
trait UsesPolylang
{
    private function setUpPolylang(): void
    {
        if (!function_exists('PLL') || !function_exists('pll_current_language')) {
            $this->markTestSkipped('Polylang loads only in the multilingual run (composer test:integration:polylang).');
        }

        $languages = PLL()->model->languages;
        // The languages are terms, gone after each test; Polylang keeps its own list.
        $languages->clean_cache();

        // Nothing is downloaded while languages are created.
        $blockHttp = static fn () => new \WP_Error('http_blocked', 'No HTTP requests in tests.');
        add_filter('pre_http_request', $blockHttp);

        foreach (['fa_IR', 'en_US', 'ar'] as $order => $locale) {
            $language = $languages->add(['locale' => $locale, 'term_group' => $order, 'no_default_cat' => true]);
            if (is_wp_error($language)) {
                $this->fail("Polylang could not add $locale: " . $language->get_error_message());
            }
        }

        remove_filter('pre_http_request', $blockHttp);

        if (pll_default_language() !== 'fa') {
            $languages->update_default('fa');
        }

        // As Polylang does once it has read the language from the address.
        add_filter('locale', static fn (string $locale): string => PLL()->curlang->locale ?? $locale);

        $this->useLanguage('fa');
    }

    /**
     * The language of the page being read, as Polylang would set it from
     * the address.
     */
    private function useLanguage(string $slug): void
    {
        PLL()->curlang = PLL()->model->get_language($slug) ?: null;
        ContentLanguage::reset();
    }

    private function postIn(string $slug, array $args = []): int
    {
        $postId = self::factory()->post->create($args + ['post_status' => 'publish']);
        pll_set_post_language($postId, $slug);

        return $postId;
    }
}
