<?php

namespace PersianKit\Tests\Unit\Support;

use Brain\Monkey\Functions;
use PersianKit\Service\Language\ContentLanguage;

/**
 * Puts the code under test on a multilingual site. Call
 * ContentLanguage::reset() in setUp, as its answers are kept per request.
 */
trait UsesLanguages
{
    /**
     * A page in the given language on a front end request, or an admin
     * screen of an admin with that profile language.
     */
    protected function inLanguage(string $locale, bool $admin = false): FakeLanguageSource
    {
        $source = new FakeLanguageSource();
        $source->current = $admin ? null : $locale;
        ContentLanguage::useSource($source);

        Functions\when('is_locale_switched')->justReturn(false);
        Functions\when('is_admin')->justReturn($admin);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('get_user_locale')->justReturn($locale);

        return $source;
    }
}
