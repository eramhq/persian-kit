<?php
/**
 * Compatibility guidance cards.
 *
 * @var array<int, array<string, mixed>> $compatibilityReports
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$compatibilityReports = $args['compatibilityReports'] ?? [];
?>
<section id="persian-kit-compatibility" class="persian-kit-compatibility" aria-labelledby="persian-kit-compatibility-title">
    <h2 id="persian-kit-compatibility-title" class="screen-reader-text"><?php esc_html_e('Compatibility', 'persian-kit'); ?></h2>

    <div class="persian-kit-compatibility__cards">
        <?php foreach ($compatibilityReports as $report) : ?>
            <details class="persian-kit-compatibility__card">
                <summary class="persian-kit-compatibility__card-header">
                    <span class="persian-kit-compatibility__icon">
                        <?php echo \PersianKit\Components\Icon::render('alert'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
                    </span>
                    <h3 class="persian-kit-compatibility__title">
                        <?php echo esc_html($report['name'] ?? ''); ?>
                        <span class="persian-kit-compatibility__type">
                            <?php
                            echo esc_html(($report['type'] ?? 'overlap') === 'supplementary'
                                ? __('Supplementary', 'persian-kit')
                                : __('Overlap', 'persian-kit'));
                            ?>
                        </span>
                    </h3>
                    <span class="persian-kit-compatibility__summary"><?php echo esc_html($report['summary'] ?? ''); ?></span>
                    <span class="persian-kit-compatibility__more"><?php esc_html_e('Review recommended settings', 'persian-kit'); ?></span>
                </summary>

                <div class="persian-kit-compatibility__body">
                    <p class="persian-kit-compatibility__intro">
                        <?php esc_html_e('Another Persian plugin does some of the same things. Use one plugin for each feature.', 'persian-kit'); ?>
                    </p>

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
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
