<?php

namespace PersianKit\Tests\Unit\Support;

use PersianKit\Service\Language\LanguageSource;

/**
 * A multilingual plugin whose answers the test sets.
 */
final class FakeLanguageSource implements LanguageSource
{
    /** @var list<string> */
    public array $languages = ['fa_IR', 'en_US', 'ar'];

    public ?string $current = null;

    public ?string $default = 'fa_IR';

    public ?string $switched = null;

    /** @var array<int, string> Locales by post ID. */
    public array $posts = [];

    /** @var array<int, string> Locales by term ID. */
    public array $terms = [];

    /** @var array<string, string> Locales by "type:id", as requestedLocale() receives them. */
    public array $requested = [];

    /** The condition currentLanguagePosts() returns. */
    public ?string $postsCondition = null;

    /** @var list<list<string>> Post types currentLanguagePosts() was asked for. */
    public array $postsAskedFor = [];

    public int $registered = 0;

    public function languages(): array
    {
        return $this->languages;
    }

    public function currentLocale(): ?string
    {
        return $this->current;
    }

    public function defaultLocale(): ?string
    {
        return $this->default;
    }

    public function postLocale(int $postId): ?string
    {
        return $this->posts[$postId] ?? null;
    }

    public function termLocale(int $termId): ?string
    {
        return $this->terms[$termId] ?? null;
    }

    public function switchedLocale(): ?string
    {
        return $this->switched;
    }

    public function requestedLocale(string $objectType, int $objectId): ?string
    {
        return $this->requested["{$objectType}:{$objectId}"] ?? null;
    }

    public function currentLanguagePosts(array $postTypes): ?string
    {
        $this->postsAskedFor[] = $postTypes;

        return $this->postsCondition;
    }

    public function register(): void
    {
        $this->registered++;
    }
}
