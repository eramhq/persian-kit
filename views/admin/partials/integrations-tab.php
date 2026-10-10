<?php
/**
 * The Integrations tab: a card for each integration, grouped by category,
 * then the supported plugins that are not active ("Also works with").
 *
 * @var array<string, array{label: string, cards: list<array<string, mixed>>}> $groups
 * @var list<array<string, mixed>>                                             $also
 * @var bool                                                                   $empty  No supported plugin is active.
 */

use PersianKit\Components\Icon;
use PersianKit\Components\View;
use PersianKit\Core\AdminPage;

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$groups = $args['groups'] ?? [];
$also = $args['also'] ?? [];
?>
<?php if (!empty($args['empty'])) : ?>
    <div class="persian-kit-empty">
        <span class="persian-kit-empty__icon">
            <?php Icon::print('integrations'); ?>
        </span>
        <p class="persian-kit-empty__title"><?php esc_html_e('None of the supported plugins are active on this site.', 'persian-kit'); ?></p>
    </div>
<?php endif; ?>

<?php foreach ($groups as $category => $group) : ?>
    <section class="persian-kit-group" aria-labelledby="persian-kit-group-<?php echo esc_attr($category); ?>">
        <h2 class="persian-kit-group__title" id="persian-kit-group-<?php echo esc_attr($category); ?>"><?php echo esc_html($group['label']); ?></h2>
        <ul class="persian-kit-integrations">
            <?php foreach ($group['cards'] as $card) : ?>
                <?php View::load('admin/partials/integration-card', ['card' => $card]); ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>

<?php if ($also !== []) : ?>
    <section class="persian-kit-group" aria-labelledby="persian-kit-group-also">
        <h2 class="persian-kit-group__title" id="persian-kit-group-also"><?php esc_html_e('Also works with', 'persian-kit'); ?></h2>
        <ul class="persian-kit-integrations">
            <?php foreach ($also as $card) : ?>
                <?php View::load('admin/partials/integration-card', ['card' => $card]); ?>
            <?php endforeach; ?>
        </ul>
        <p class="persian-kit-also__note">
            <?php esc_html_e('Activate one and Persian Kit works with it by itself.', 'persian-kit'); ?>
            <a href="<?php echo esc_url(AdminPage::DOCS_URL . 'compatibility/'); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Docs', 'persian-kit'); ?>
                <span class="screen-reader-text"><?php esc_html_e('(opens in a new tab)', 'persian-kit'); ?></span>
            </a>
        </p>
    </section>
<?php endif; ?>
