<?php
/**
 * Compatibility guidance cards.
 *
 * @var array<int, array<string, mixed>> $compatibilityReports
 */

defined('ABSPATH') || exit;
?>
<section id="persian-kit-compatibility" class="persian-kit-compatibility">
    <h2><?php esc_html_e('Compatibility', 'persian-kit'); ?></h2>
    <p class="persian-kit-compatibility__intro">
        <?php esc_html_e('Another Persian plugin is already handling some of the same features. To avoid mixed results, use only one plugin for each feature area.', 'persian-kit'); ?>
    </p>

    <div class="persian-kit-compatibility__cards">
        <?php foreach ($compatibilityReports as $report) : ?>
            <article class="persian-kit-compatibility__card">
                <header class="persian-kit-compatibility__card-header">
                    <h3 class="persian-kit-compatibility__title"><?php echo esc_html($report['name'] ?? ''); ?></h3>
                    <span class="persian-kit-compatibility__type">
                        <?php
                        echo esc_html(($report['type'] ?? 'overlap') === 'supplementary'
                            ? __('Supplementary', 'persian-kit')
                            : __('Overlap', 'persian-kit'));
                        ?>
                    </span>
                </header>

                <p class="persian-kit-compatibility__summary"><?php echo esc_html($report['summary'] ?? ''); ?></p>

                <?php if (!empty($report['handles']) && is_array($report['handles'])) : ?>
                    <div class="persian-kit-compatibility__block">
                        <strong><?php esc_html_e('This plugin is handling:', 'persian-kit'); ?></strong>
                        <ul class="persian-kit-compatibility__handles">
                            <?php foreach ($report['handles'] as $handle) : ?>
                                <li><?php echo esc_html($handle); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($report['recommendations']) && is_array($report['recommendations'])) : ?>
                    <div class="persian-kit-compatibility__block">
                        <strong><?php esc_html_e('Recommended Persian Kit settings', 'persian-kit'); ?></strong>
                        <ul class="persian-kit-compatibility__recommendations">
                            <?php foreach ($report['recommendations'] as $recommendation) : ?>
                                <?php
                                $action = $recommendation['action'] ?? 'no_change';
                                ?>
                                <li class="persian-kit-compatibility__recommendation">
                                    <span class="persian-kit-compatibility__badge persian-kit-compatibility__badge--<?php echo esc_attr($action); ?>">
                                        <?php echo esc_html($recommendation['action_label'] ?? ''); ?>
                                    </span>
                                    <div class="persian-kit-compatibility__recommendation-copy">
                                        <span class="persian-kit-compatibility__instruction">
                                            <?php echo esc_html($recommendation['instruction'] ?? ''); ?>
                                        </span>
                                        <span class="persian-kit-compatibility__current">
                                            <?php echo esc_html($recommendation['current_label'] ?? ''); ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($report['note']) && is_string($report['note'])) : ?>
                    <p class="persian-kit-compatibility__note"><?php echo esc_html($report['note']); ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
