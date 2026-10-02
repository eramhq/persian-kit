<?php

namespace PersianKit\Modules\CharNormalization;

use PersianKit\Dependencies\Eram\Abzar\Exception\AbzarException;
use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Dependencies\Eram\Abzar\Text\HalfSpaceFixer;
use PersianKit\Dependencies\Eram\Abzar\Text\HtmlSegmenter;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Fixes text as it is saved: Arabic ي/ك become Persian ی/ک (and Arabic-Indic
 * digits Persian ones) in posts, menu items, comments and terms, and, as a
 * separate option, half-spaces are added inside compound words in posts.
 * HTML tags, code and character references in content are left alone.
 *
 * On multilingual sites only Persian content is fixed: Arabic keeps its
 * own ي and ك.
 */
class SaveNormalizer
{
    private CharNormalizer $normalizer;

    private bool $fixLetters;

    private bool $fixHalfSpaces;

    public function __construct(CharNormalizer $normalizer, bool $fixLetters, bool $fixHalfSpaces)
    {
        $this->normalizer = $normalizer;
        $this->fixLetters = $fixLetters;
        $this->fixHalfSpaces = $fixHalfSpaces;
    }

    public function register(): void
    {
        if ($this->fixLetters || $this->fixHalfSpaces) {
            $this->addFilter('wp_insert_post_data', [$this, 'filterPostData'], 2);
        }

        if (!$this->fixLetters) {
            return;
        }

        $this->addFilter('preprocess_comment', [$this, 'filterComment']);
        $this->addFilter('pre_term_name', [$this, 'filterText']);
        $this->addFilter('pre_term_description', [$this, 'filterContent']);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function filterPostData(array $data, array $postarr): array
    {
        if (!$this->shouldNormalize($data, $postarr)) {
            return $data;
        }

        foreach (['post_title', 'post_excerpt'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->fixText($data[$field], $this->fixHalfSpaces);
            }
        }

        if (isset($data['post_content']) && is_string($data['post_content'])) {
            $data['post_content'] = $this->fixContent($data['post_content'], $this->fixHalfSpaces);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $commentdata
     * @return array<string, mixed>
     */
    public function filterComment(array $commentdata): array
    {
        // A comment is in its post's language.
        if (!ContentLanguage::writesPersian('post', (int) ($commentdata['comment_post_ID'] ?? 0))) {
            return $commentdata;
        }

        if (isset($commentdata['comment_author']) && is_string($commentdata['comment_author'])) {
            $commentdata['comment_author'] = $this->fixText($commentdata['comment_author'], false);
        }

        if (isset($commentdata['comment_content']) && is_string($commentdata['comment_content'])) {
            $commentdata['comment_content'] = $this->fixContent($commentdata['comment_content'], false);
        }

        return $commentdata;
    }

    /**
     * A term's name. Core passes no term, so the request tells its language.
     */
    public function filterText(mixed $text): mixed
    {
        return is_string($text) && ContentLanguage::writesPersian('term') ? $this->fixText($text, false) : $text;
    }

    /**
     * A term's description.
     */
    public function filterContent(mixed $html): mixed
    {
        return is_string($html) && ContentLanguage::writesPersian('term') ? $this->fixContent($html, false) : $html;
    }

    private function fixText(string $text, bool $halfSpaces): string
    {
        if ($this->fixLetters) {
            $text = $this->normalizer->normalize($text);
        }

        return $halfSpaces ? HalfSpaceFixer::fix($text) : $text;
    }

    private function fixContent(string $html, bool $halfSpaces): string
    {
        if ($html === '' || (!$this->fixLetters && !$halfSpaces)) {
            return $html;
        }

        try {
            return HtmlSegmenter::transformText($html, fn (string $text): string => $this->fixText($text, $halfSpaces));
        } catch (AbzarException) {
            // Markup abzar cannot segment is saved as-is rather than blocking the save.
            return $html;
        }
    }

    /**
     * Public post types and menu items in Persian; not auto-drafts,
     * revisions or autosaves.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     */
    private function shouldNormalize(array $data, array $postarr): bool
    {
        $postType = $data['post_type'] ?? $postarr['post_type'] ?? '';
        if (!is_string($postType) || $postType === '') {
            return false;
        }

        $postTypeObject = get_post_type_object($postType);
        if (!$postTypeObject || (empty($postTypeObject->public) && $postType !== 'nav_menu_item')) {
            return false;
        }

        $postStatus = $data['post_status'] ?? $postarr['post_status'] ?? '';
        if ($postStatus === 'auto-draft') {
            return false;
        }

        $postId = isset($postarr['ID']) ? (int) $postarr['ID'] : 0;
        if ($postId > 0 && (wp_is_post_revision($postId) || wp_is_post_autosave($postId))) {
            return false;
        }

        $postContext = $postId > 0 ? get_post($postId) : null;
        if (!$postContext) {
            $postContext = (object) $postarr;
        }

        /**
         * Whether a post's text is fixed as it is saved.
         *
         * @param bool                 $normalize   Whether the post is in Persian; true except on
         *                                          multilingual sites.
         * @param object               $postContext The post, or the data it is created from.
         * @param array<string, mixed> $data        The post data about to be saved.
         * @param array<string, mixed> $postarr     The post data as passed to wp_insert_post().
         */
        return (bool) apply_filters('persian_kit_should_normalize', ContentLanguage::writesPersian('post', $postId), $postContext, $data, $postarr);
    }

    private function addFilter(string $hook, callable $callback, int $acceptedArgs = 1): void
    {
        if (apply_filters('persian_kit_char_normalization', true, $hook)) {
            add_filter($hook, $callback, 10, $acceptedArgs);
        }
    }
}
