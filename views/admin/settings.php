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

$tabUrl = static function (string $tab): string {
    return admin_url('admin.php?page=' . \PersianKit\Core\AdminPage::MENU_SLUG . '&tab=' . $tab);
};

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
    <h1><?php esc_html_e('Persian Kit', 'persian-kit'); ?></h1>
    <p class="persian-kit-page-description">
        <?php esc_html_e('Changes apply when you save.', 'persian-kit'); ?>
    </p>

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
            ><?php echo esc_html($tabLabel); ?></a>
        <?php endforeach; ?>
    </nav>

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
</div>
