<?php
/**
 * Main settings page template.
 *
 * @var array<string, array>        $modules              Module data by module key.
 * @var array<string, list<string>> $groups               Module keys by tab.
 * @var array<string, string>       $tabs                 Tab labels by tab name.
 * @var string                      $activeTab            The tab shown first.
 * @var array                       $compatibilityReports Compatibility guidance cards.
 * @var bool                        $showWelcome          Whether to show the first-run notice.
 * @var string                      $dismissWelcomeUrl    URL that hides the first-run notice.
 */

defined('ABSPATH') || exit;

// Variables here are local to View::load(), not globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$modules = $args['modules'] ?? [];
$groups = $args['groups'] ?? [];
$tabs = $args['tabs'] ?? [];
$activeTab = $args['activeTab'] ?? 'display';
$compatibilityReports = $args['compatibilityReports'] ?? [];
$showWelcome = !empty($args['showWelcome']);

// Every panel but the active one starts hidden. Hidden inputs still submit,
// so Save sends the settings of every tab.
$panelAttributes = static function (string $tab) use ($activeTab): string {
    return sprintf(
        'id="persian-kit-panel-%1$s" class="persian-kit-panel" role="tabpanel" aria-labelledby="persian-kit-tab-%1$s"%2$s',
        esc_attr($tab),
        $tab === $activeTab ? '' : ' hidden'
    );
};
?>
<div class="wrap persian-kit-wrap" x-data="persianKitTabs">
    <?php
    \PersianKit\Components\View::load('admin/partials/header', [
        'tabs'      => $tabs,
        'activeTab' => $activeTab,
    ]);
    ?>
    <?php // WordPress moves admin notices to just after this line, below the header. ?>
    <hr class="wp-header-end">

    <div class="persian-kit-content">
        <?php
        // "Settings saved." from options.php; WordPress shows it by itself only under the Settings menu.
        settings_errors();
        ?>

        <?php if ($showWelcome) : ?>
            <?php
            \PersianKit\Components\View::load('admin/partials/welcome', [
                'dismissWelcomeUrl' => $args['dismissWelcomeUrl'] ?? '',
            ]);
            ?>
        <?php endif; ?>

        <?php if (!empty($compatibilityReports)) : ?>
            <?php
            \PersianKit\Components\View::load('admin/partials/compatibility-guidance', [
                'compatibilityReports' => $compatibilityReports,
            ]);
            ?>
        <?php endif; ?>

        <form id="persian-kit-settings-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
            <?php settings_fields(\PersianKit\Core\SettingsRegistrar::GROUP); ?>

            <?php foreach ($groups as $tab => $moduleKeys) : ?>
                <?php
                if ($tab === 'tools') {
                    continue;
                }
                ?>
                <div <?php echo $panelAttributes($tab); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $panelAttributes. ?>>
                    <?php foreach ($moduleKeys as $moduleKey) : ?>
                        <?php
                        $moduleData = $modules[$moduleKey];
                        \PersianKit\Components\View::load('admin/partials/module-toggle', [
                            'moduleKey'         => $moduleData['key'],
                            'moduleLabel'       => $moduleData['label'],
                            'moduleIcon'        => $moduleData['icon'],
                            'moduleDescription' => $moduleData['description'],
                            'inactivePlugins'   => $moduleData['inactive'],
                            'module'            => $moduleData['instance'],
                            'moduleSettings'    => $moduleData['settings'],
                        ]);
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="persian-kit-savebar"<?php echo $activeTab === 'tools' ? ' hidden' : ''; ?>>
                <?php submit_button(__('Save changes', 'persian-kit'), 'primary', 'submit', false); ?>
                <span class="persian-kit-savebar__status" x-show="dirty" x-cloak>
                    <?php esc_html_e('Unsaved changes', 'persian-kit'); ?>
                </span>
            </div>
        </form>

        <?php // Outside the settings form: the fix tool's inputs are not settings. ?>
        <div <?php echo $panelAttributes('tools'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $panelAttributes. ?>>
            <?php \PersianKit\Components\View::load('admin/partials/tools'); ?>
        </div>

        <?php \PersianKit\Components\View::load('admin/partials/footer'); ?>
    </div>
</div>
