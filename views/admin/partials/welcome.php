<?php
/**
 * Welcome notice shown after a fresh install until it is dismissed.
 *
 * @var string $dismissWelcomeUrl Nonce-protected URL that hides the notice.
 */

defined('ABSPATH') || exit;
?>
<div class="notice notice-info persian-kit-welcome">
    <h2><?php esc_html_e('Welcome to Persian Kit', 'persian-kit'); ?></h2>
    <p><?php esc_html_e('Turn each part on or off below. Your saved posts are not changed.', 'persian-kit'); ?></p>
    <p><?php esc_html_e('Persian digits and fixing letters on save start off.', 'persian-kit'); ?></p>
    <p>
        <a class="button button-primary" href="<?php echo esc_url($args['dismissWelcomeUrl'] ?? ''); ?>">
            <?php esc_html_e('Got it', 'persian-kit'); ?>
        </a>
    </p>
</div>
