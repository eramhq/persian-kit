<?php

namespace PersianKit\Service\Language;

defined('ABSPATH') || exit;

/**
 * What a multilingual plugin (Polylang, WPML) knows about languages, as
 * WordPress locales (fa_IR, en_US). Each method returns null when the plugin
 * does not know.
 *
 * @internal Implemented here for each supported plugin, and by fakes in tests.
 */
interface LanguageSource
{
    /**
     * Locales of the languages set up on the site.
     *
     * @return list<string>
     */
    public function languages(): array;

    /** The language of the page being viewed, or of the content being edited in the admin. */
    public function currentLocale(): ?string;

    public function defaultLocale(): ?string;

    public function postLocale(int $postId): ?string;

    public function termLocale(int $termId): ?string;

    /**
     * A language the plugin switched to during the request, such as for an
     * email in the customer's language.
     */
    public function switchedLocale(): ?string;

    /**
     * The language this request's form fields or REST parameters give the
     * object being saved. An ID of 0 means the object the request saves.
     *
     * @param 'post'|'term' $objectType
     */
    public function requestedLocale(string $objectType, int $objectId): ?string;

    /** Hooks the source needs to follow the language during the request. */
    public function register(): void;
}
