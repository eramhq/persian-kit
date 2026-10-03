<?php
/**
 * Settings page header: the logo and version, help links and the tabs.
 *
 * @var array<string, string> $tabs      Tab labels by tab name.
 * @var string                $activeTab The tab shown first.
 */

use PersianKit\Components\Icon;
use PersianKit\Core\AdminPage;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$tabs = $args['tabs'] ?? [];
$activeTab = $args['activeTab'] ?? 'display';

$tabUrl = static function (string $tab): string {
    return admin_url('admin.php?page=' . AdminPage::MENU_SLUG . '&tab=' . $tab);
};

$links = [
    [AdminPage::REPO_URL . '/blob/main/CHANGELOG.md', 'sparkle', __("What's new", 'persian-kit')],
    [AdminPage::REPO_URL . '#readme', 'book', __('Docs', 'persian-kit')],
    [AdminPage::REPO_URL . '/issues', 'chat', __('Support', 'persian-kit')],
];
?>
<div class="persian-kit-header">
    <div class="persian-kit-header__inner">
        <div class="persian-kit-header__top">
            <div class="persian-kit-brand">
                <img class="persian-kit-brand__logo" src="<?php echo esc_url(Icon::logoUrl()); ?>" alt="" width="34" height="34">
                <h1 class="persian-kit-brand__name"><?php esc_html_e('Persian Kit', 'persian-kit'); ?></h1>
                <span class="persian-kit-brand__fa" lang="fa" dir="rtl" aria-hidden="true">کیت فارسی</span>
                <span class="persian-kit-version">
                    <span class="screen-reader-text"><?php esc_html_e('Version', 'persian-kit'); ?></span>
                    <?php echo esc_html(PERSIAN_KIT_VERSION); ?>
                </span>
            </div>

            <nav class="persian-kit-links" aria-label="<?php esc_attr_e('Persian Kit help', 'persian-kit'); ?>">
                <?php foreach ($links as [$url, $icon, $label]) : ?>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php Icon::print($icon); ?>
                        <?php echo esc_html($label); ?>
                        <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'persian-kit'); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <p class="persian-kit-page-description">
            <?php esc_html_e('Changes apply when you save.', 'persian-kit'); ?>
        </p>

        <nav class="nav-tab-wrapper persian-kit-tabs" role="tablist" aria-label="<?php esc_attr_e('Persian Kit settings', 'persian-kit'); ?>">
            <?php foreach ($tabs as $tab => $tabLabel) : ?>
                <a
                    href="<?php echo esc_url($tabUrl($tab)); ?>"
                    id="persian-kit-tab-<?php echo esc_attr($tab); ?>"
                    class="nav-tab<?php echo $tab === $activeTab ? ' nav-tab-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $tab === $activeTab ? 'true' : 'false'; ?>"
                    aria-controls="persian-kit-panel-<?php echo esc_attr($tab); ?>"
                    data-tab="<?php echo esc_attr($tab); ?>"
                ><?php Icon::print($tab); ?><span><?php echo esc_html($tabLabel); ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
