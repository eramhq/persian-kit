<?php
/**
 * Welcome notice shown after a fresh install until it is dismissed.
 *
 * @var array  $modules           Module data array (label, description, settings).
 * @var string $dismissWelcomeUrl Nonce-protected URL that hides the notice.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$modules = $args['modules'] ?? [];
$dismissWelcomeUrl = $args['dismissWelcomeUrl'] ?? '';
?>
<div class="notice notice-info persian-kit-welcome">
    <h2><?php esc_html_e('Welcome to Persian Kit', 'persian-kit'); ?></h2>
    <p>
        <?php esc_html_e('Each module below can be switched on or off on its own. Here is what each one does on your site right now.', 'persian-kit'); ?>
    </p>
    <ul class="persian-kit-welcome__modules">
        <?php foreach ($modules as $moduleData) : ?>
            <li>
                <strong><?php echo esc_html($moduleData['label']); ?></strong>
                <span class="persian-kit-welcome__state">
                    (<?php echo !empty($moduleData['settings']['enabled']) ? esc_html__('on', 'persian-kit') : esc_html__('off', 'persian-kit'); ?>)
                </span>
                — <?php echo esc_html($moduleData['description']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p>
        <?php esc_html_e('Your saved posts are not changed. Digit conversion and fixing letters on save start off; turn them on below if you want them.', 'persian-kit'); ?>
    </p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url($dismissWelcomeUrl); ?>">
            <?php esc_html_e('Got it, hide this', 'persian-kit'); ?>
        </a>
    </p>
</div>
