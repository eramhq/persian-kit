<?php
/**
 * Settings page footer: a khatam rule, then the name, version and links,
 * centred like the colophon at the end of a book. It stands in for
 * WordPress's own footer line, which AdminPage empties on this page.
 */

use PersianKit\Components\Icon;
use PersianKit\Core\AdminPage;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$links = [
    [AdminPage::SUPPORT_URL, __('Send feedback', 'persian-kit')],
    [AdminPage::REPO_URL . '/issues/new', __('Report a bug', 'persian-kit')],
    [AdminPage::REPO_URL, __('Source on GitHub', 'persian-kit')],
];
$newTab = __('(opens in a new tab)', 'persian-kit');
?>
<footer class="persian-kit-footer">
    <div class="persian-kit-footer__rule" aria-hidden="true">
        <?php echo Icon::render('khatam'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
    </div>

    <div class="persian-kit-footer__brand">
        <img src="<?php echo esc_url(Icon::logoUrl()); ?>" alt="" width="22" height="22">
        <strong><?php esc_html_e('Persian Kit', 'persian-kit'); ?></strong>
        <a class="persian-kit-version" href="<?php echo esc_url(AdminPage::REPO_URL . '/blob/main/CHANGELOG.md'); ?>" target="_blank" rel="noopener noreferrer">
            <span class="screen-reader-text"><?php esc_html_e('Version', 'persian-kit'); ?></span>
            <?php echo esc_html(PERSIAN_KIT_VERSION); ?>
            <span class="screen-reader-text"><?php echo esc_html($newTab); ?></span>
        </a>
    </div>

    <p class="persian-kit-footer__tagline"><?php esc_html_e('Made for Persian WordPress. Free and open source.', 'persian-kit'); ?></p>

    <nav class="persian-kit-footer__links" aria-label="<?php esc_attr_e('About Persian Kit', 'persian-kit'); ?>">
        <?php foreach ($links as [$url, $label]) : ?>
            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($label); ?>
                <span class="screen-reader-text"><?php echo esc_html($newTab); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</footer>
