<?php

namespace PersianKit\Service\Import;

use PersianKit\Core\ConflictDetector;

defined('ABSPATH') || exit;

/**
 * While a switch is waiting, one line on the Plugins screen leads back to
 * it: deactivating there is the step the switch waits on.
 */
class PluginsScreenNotice
{
    private SourceRegistry $sources;

    public function __construct(SourceRegistry $sources)
    {
        $this->sources = $sources;
    }

    public function register(): void
    {
        add_action('admin_notices', [$this, 'render']);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen instanceof \WP_Screen || $screen->id !== 'plugins') {
            return;
        }

        $job = ImportJob::load();
        if ($job === null || $job->isFinished()) {
            return;
        }

        $source = $this->sources->get($job->source);
        if ($source === null) {
            return;
        }

        $message = $source->readyToImport() === null
            /* translators: %s: plugin name, such as Parsi Date. */
            ? sprintf(__('%s is inactive. Continue the switch to Persian Kit to import its settings and data.', 'persian-kit'), $source->name())
            /* translators: %s: plugin name, such as Parsi Date. */
            : sprintf(__('The switch from %s to Persian Kit is waiting.', 'persian-kit'), $source->name());
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Persian Kit', 'persian-kit'); ?>:</strong>
                <?php echo esc_html($message); ?>
                <a href="<?php echo esc_url(ConflictDetector::switchUrl()); ?>">
                    <?php
                    /* translators: %s: plugin name, such as Parsi Date. */
                    echo esc_html(sprintf(__('Continue the switch from %s', 'persian-kit'), $source->name()));
                    ?>
                </a>
            </p>
        </div>
        <?php
    }
}
