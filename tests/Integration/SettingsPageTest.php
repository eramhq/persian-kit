<?php

namespace PersianKit\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PersianKit\Bootstrap;
use PersianKit\Core\AdminPage;
use PersianKit\Core\ConflictDetector;
use PersianKit\Core\ModuleRegistry;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\Forms\AcfModule;
use PersianKit\Modules\Forms\Cf7Module;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Tests\Integration\Support\FakesPluginState;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class SettingsPageTest extends WordPressIntegrationTestCase
{
    /**
     * Every settings field of the core modules, with its value and whether
     * a checkbox starts checked, on a fresh install. The order may change.
     * [name, type, value, checked]
     */
    private const FIELDS = [
        ['persian_kit_settings[digit_conversion][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[digit_conversion][enabled]', 'checkbox', '1', false],
        ['persian_kit_settings[digit_conversion][dates]', 'hidden', '0', false],
        ['persian_kit_settings[digit_conversion][dates]', 'checkbox', '1', true],
        ['persian_kit_settings[digit_conversion][numbers]', 'hidden', '0', false],
        ['persian_kit_settings[digit_conversion][numbers]', 'checkbox', '1', true],
        ['persian_kit_settings[digit_conversion][prices]', 'hidden', '0', false],
        ['persian_kit_settings[digit_conversion][prices]', 'checkbox', '1', true],
        ['persian_kit_settings[date_conversion][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[date_conversion][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[date_conversion][global_conversion]', 'hidden', '0', false],
        ['persian_kit_settings[date_conversion][global_conversion]', 'checkbox', '1', false],
        ['persian_kit_settings[date_conversion][jalali_archives]', 'hidden', '0', false],
        ['persian_kit_settings[date_conversion][jalali_archives]', 'checkbox', '1', true],
        ['persian_kit_settings[date_conversion][jalali_permalinks]', 'hidden', '0', false],
        ['persian_kit_settings[date_conversion][jalali_permalinks]', 'checkbox', '1', false],
        ['persian_kit_settings[char_normalization][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[char_normalization][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[char_normalization][normalize_on_save]', 'hidden', '0', false],
        ['persian_kit_settings[char_normalization][normalize_on_save]', 'checkbox', '1', false],
        ['persian_kit_settings[char_normalization][teh_marbuta]', 'hidden', '0', false],
        ['persian_kit_settings[char_normalization][teh_marbuta]', 'checkbox', '1', false],
        ['persian_kit_settings[char_normalization][half_space_fix]', 'hidden', '0', false],
        ['persian_kit_settings[char_normalization][half_space_fix]', 'checkbox', '1', false],
        ['persian_kit_settings[admin_font][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[admin_font][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[admin_font][font]', 'select', 'vazirmatn', false],
        ['persian_kit_settings[zwnj_editor][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[zwnj_editor][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[utilities][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[utilities][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[utilities][persian_slugs]', 'hidden', '0', false],
        ['persian_kit_settings[utilities][persian_slugs]', 'checkbox', '1', true],
    ];

    /** The fields each integration adds while its plugin is active. */
    private const INTEGRATION_FIELDS = [
        'woocommerce' => [
            ['persian_kit_settings[woocommerce][enabled]', 'hidden', '0', false],
            ['persian_kit_settings[woocommerce][enabled]', 'checkbox', '1', true],
            ['persian_kit_settings[woocommerce][checkout_normalize]', 'hidden', '0', false],
            ['persian_kit_settings[woocommerce][checkout_normalize]', 'checkbox', '1', true],
            ['persian_kit_settings[woocommerce][checkout_validate]', 'hidden', '0', false],
            ['persian_kit_settings[woocommerce][checkout_validate]', 'checkbox', '1', true],
            ['persian_kit_settings[woocommerce][city_select]', 'hidden', '0', false],
            ['persian_kit_settings[woocommerce][city_select]', 'checkbox', '1', false],
            ['persian_kit_settings[woocommerce][national_id]', 'select', 'off', false],
            ['persian_kit_settings[woocommerce][dates_admin]', 'hidden', '0', false],
            ['persian_kit_settings[woocommerce][dates_admin]', 'checkbox', '1', true],
        ],
        'cf7' => [
            ['persian_kit_settings[cf7][enabled]', 'hidden', '0', false],
            ['persian_kit_settings[cf7][enabled]', 'checkbox', '1', true],
        ],
        'acf' => [
            ['persian_kit_settings[acf][enabled]', 'hidden', '0', false],
            ['persian_kit_settings[acf][enabled]', 'checkbox', '1', true],
        ],
    ];

    private string $requestUri = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '';

        require_once ABSPATH . 'wp-admin/includes/screen.php';

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        delete_option('active_plugins');
        delete_option('persian_kit_settings');
        delete_option(AdminPage::SEEN_OPTION);

        Bootstrap::activate(false);

        set_current_screen('toplevel_page_persian-kit');
    }

    protected function tearDown(): void
    {
        unset($_GET['tab']);
        delete_transient(\PersianKit\Modules\Forms\Cf7FieldUsage::TRANSIENT);
        $_SERVER['REQUEST_URI'] = $this->requestUri;

        parent::tearDown();
    }

    public function test_form_keeps_every_settings_field_and_value(): void
    {
        $xpath = $this->render();

        $fields = [];
        foreach ($xpath->query('//form[@id="persian-kit-settings-form"]//*[starts-with(@name, "persian_kit_settings")]') as $field) {
            $this->assertInstanceOf(DOMElement::class, $field);
            $fields[] = $this->describe($xpath, $field);
        }

        $expected = self::FIELDS;
        foreach (self::INTEGRATION_FIELDS as $key => $integrationFields) {
            if ($this->module($key)->isAvailable()) {
                $expected = array_merge($expected, $integrationFields);
            }
        }

        // Provinces you deliver to: an empty list by default, so none is ticked.
        if ($this->module('woocommerce')->isAvailable()) {
            $expected[] = ['persian_kit_settings[woocommerce][allowed_states][]', 'hidden', '', false];
            foreach (array_keys(WC()->countries->get_states('IR')) as $code) {
                $expected[] = ['persian_kit_settings[woocommerce][allowed_states][]', 'checkbox', $code, false];
            }
        }

        $this->assertEqualsCanonicalizing($expected, $fields);
        $this->assertCount(count($expected), $fields);
    }

    public function test_settings_fields_sit_only_inside_the_form(): void
    {
        $xpath = $this->render();

        $this->assertSame(
            0,
            $xpath->query('//*[starts-with(@name, "persian_kit_settings")][not(ancestor::form[@id="persian-kit-settings-form"])]')->length
        );
    }

    public function test_page_has_its_tabs_and_shows_the_first_by_default(): void
    {
        $xpath = $this->render();

        $expected = $this->module('woocommerce')->isAvailable()
            ? AdminPage::TABS
            : array_values(array_diff(AdminPage::TABS, ['woocommerce']));
        $tabs = $xpath->query('//*[@role="tablist"]/*[@role="tab"]');
        $this->assertSame($expected, array_map(
            static fn(DOMElement $tab): string => $tab->getAttribute('data-tab'),
            iterator_to_array($tabs)
        ));

        $this->assertSame(['display'], $this->selectedTabs($xpath));
        $this->assertSame(['display'], $this->visiblePanels($xpath));
    }

    public function test_tab_parameter_picks_the_active_tab(): void
    {
        $_GET['tab'] = 'writing';
        $xpath = $this->render();

        $this->assertSame(['writing'], $this->selectedTabs($xpath));
        $this->assertSame(['writing'], $this->visiblePanels($xpath));
        $this->assertSame(1, $xpath->query('//a[@data-tab="writing"][contains(@class, "nav-tab-active")]')->length);
        $this->assertSame(0, $xpath->query('//a[@data-tab!="writing"][contains(@class, "nav-tab-active")]')->length);
    }

    public function test_unknown_tab_falls_back_to_the_first(): void
    {
        $_GET['tab'] = 'nope';
        $xpath = $this->render();

        $this->assertSame(['display'], $this->selectedTabs($xpath));
    }

    public function test_modules_sit_on_their_tabs(): void
    {
        $xpath = $this->render();

        $tabs = AdminPage::GROUPS + ['woocommerce' => 'woocommerce', 'cf7' => 'integrations', 'acf' => 'integrations'];
        foreach ($tabs as $moduleKey => $tab) {
            if (!$this->module($moduleKey)->isAvailable()) {
                continue;
            }

            $this->assertSame(
                1,
                $xpath->query(sprintf(
                    '//*[@id="persian-kit-panel-%s"]//input[@name="persian_kit_settings[%s][enabled]"][@type="checkbox"]',
                    $tab,
                    $moduleKey
                ))->length,
                "$moduleKey is not on the $tab tab"
            );
        }
    }

    public function test_without_woocommerce_its_tab_is_gone_and_its_link_shows_the_first_tab(): void
    {
        $_GET['tab'] = 'woocommerce';
        $xpath = $this->render(['woocommerce' => $this->withPlugin(WooCommerceModule::class, false)]);

        $this->assertSame(0, $xpath->query('//*[@data-tab="woocommerce"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="persian-kit-panel-woocommerce"]')->length);
        $this->assertSame(['display'], $this->selectedTabs($xpath));
        // Nothing is sent, so the stored settings are kept.
        $this->assertSame(0, $xpath->query('//*[starts-with(@name, "persian_kit_settings[woocommerce]")]')->length);
        // WooCommerce is listed under "Also works with".
        $this->assertSame(1, $xpath->query('//*[@id="persian-kit-group-also"]/following-sibling::ul//*[@id="persian-kit-module-woocommerce-name"]')->length);
    }

    public function test_the_woocommerce_tab_has_the_module_card_and_its_sections(): void
    {
        $xpath = $this->render(['woocommerce' => $this->withPlugin(WooCommerceModule::class, true)]);
        $panel = '//*[@id="persian-kit-panel-woocommerce"]';

        $this->assertSame(1, $xpath->query($panel . '//input[@type="checkbox"][@name="persian_kit_settings[woocommerce][enabled]"]')->length);
        $this->assertSame(['checkout', 'prices', 'dates'], array_map(
            static fn (DOMElement $section): string => $section->getAttribute('id'),
            iterator_to_array($xpath->query($panel . '//section[contains(@class, "persian-kit-section")]'))
        ));
        // Each section has a real heading and a link from the module's card.
        $this->assertSame(3, $xpath->query($panel . '//section//h2')->length);
        $this->assertSame(['#checkout', '#prices', '#dates'], array_map(
            static fn (DOMElement $link): string => $link->getAttribute('href'),
            iterator_to_array($xpath->query($panel . '//nav[contains(@class, "persian-kit-jump")]/a'))
        ));
        // The sections hide while the module is off.
        $this->assertSame(3, $xpath->query($panel . '//section[@x-show="enabled"]')->length);
    }

    public function test_the_province_grid_shows_the_saved_provinces(): void
    {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        update_option('persian_kit_settings', ['woocommerce' => ['enabled' => true, 'allowed_states' => ['THR']]]);
        $xpath = $this->render(['woocommerce' => $this->withPlugin(WooCommerceModule::class, true)]);
        $boxes = '//*[@id="persian-kit-panel-woocommerce"]//input[@type="checkbox"][@name="persian_kit_settings[woocommerce][allowed_states][]"]';

        $this->assertSame(31, $xpath->query($boxes)->length);
        $this->assertSame(['THR'], array_map(
            static fn (DOMElement $box): string => $box->getAttribute('value'),
            iterator_to_array($xpath->query($boxes . '[@checked]'))
        ));
        $this->assertSame('only', $xpath->query('//input[@type="radio"][@checked]')->item(0)?->getAttribute('value'));
    }

    public function test_the_woocommerce_tab_repeats_the_advice_of_an_overlapping_plugin(): void
    {
        update_option('active_plugins', ['persian-woocommerce/woocommerce-persian.php']);

        $xpath = $this->render(['woocommerce' => $this->withPlugin(WooCommerceModule::class, true)]);

        $advice = $xpath->query('//*[@id="persian-kit-panel-woocommerce"]//*[contains(concat(" ", @class, " "), " persian-kit-advice ")]');
        $this->assertSame(1, $advice->length);
        $this->assertInstanceOf(DOMElement::class, $advice->item(0));
        // Gone once WooCommerce is switched off here.
        $this->assertSame('enabled', $advice->item(0)->getAttribute('x-show'));
        $this->assertStringContainsString('Persian WooCommerce', $advice->item(0)->textContent);
        $this->assertSame('#persian-kit-compatibility', $xpath->evaluate('string(.//a/@href)', $advice->item(0)));
        // The page-top card stays as well.
        $this->assertSame(1, $xpath->query('//*[@id="persian-kit-compatibility"]')->length);
    }

    public function test_an_integration_whose_plugin_is_not_active_is_listed_with_its_plugin_page(): void
    {
        $xpath = $this->render(['acf' => $this->withPlugin(AcfModule::class, false)]);

        $card = '//*[@id="persian-kit-group-also"]/following-sibling::ul/li[.//*[@id="persian-kit-module-acf-name"]]';
        $this->assertSame(1, $xpath->query($card)->length);
        $this->assertSame('https://wordpress.org/plugins/advanced-custom-fields/', $xpath->evaluate("string($card//a/@href)"));
        $this->assertSame('Not active on this site', trim($xpath->evaluate("string($card//*[contains(@class, 'persian-kit-pill')])")));
        $this->assertSame(0, $xpath->query('//*[@name="persian_kit_settings[acf][enabled]"]')->length);
    }

    public function test_an_integration_seen_before_says_its_settings_are_kept(): void
    {
        update_option(AdminPage::SEEN_OPTION, ['acf']);

        $xpath = $this->render(['acf' => $this->withPlugin(AcfModule::class, false)]);

        $this->assertSame(
            'Your settings are kept',
            trim($xpath->evaluate('string(//li[.//*[@id="persian-kit-module-acf-name"]]//*[contains(@class, "persian-kit-pill")])'))
        );
    }

    public function test_an_integration_that_cannot_run_says_why(): void
    {
        $settings = $this->settings();
        $old = new class ($settings) extends AcfModule {
            public function requiredPlugins(): array
            {
                return [['name' => 'ACF', 'check' => fn () => true, 'version' => fn () => '5.0', 'minVersion' => '6.0']];
            }
        };

        $xpath = $this->render(['acf' => $old]);

        $reason = $xpath->query('//*[@id="persian-kit-module-acf-reason"]');
        $this->assertSame(1, $reason->length);
        $this->assertSame('Needs ACF 6.0 or newer (this site has 5.0).', trim($reason->item(0)->textContent));

        $toggle = $xpath->query('//input[@aria-labelledby="persian-kit-module-acf-name"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $toggle);
        $this->assertTrue($toggle->hasAttribute('disabled'));
        $this->assertFalse($toggle->hasAttribute('name'));
        $this->assertStringContainsString('persian-kit-module-acf-reason', $toggle->getAttribute('aria-describedby'));
    }

    public function test_an_integration_activated_later_is_new_until_the_tab_is_opened(): void
    {
        $acf = $this->withPlugin(AcfModule::class, true);

        // The first visit records what is already there: no badges.
        $xpath = $this->render(['acf' => $this->withPlugin(AcfModule::class, false)]);
        $this->assertSame(0, $xpath->query('//*[contains(@class, "persian-kit-badge")]')->length);

        // ACF is activated; another tab is open, so the badge stays.
        $xpath = $this->render(['acf' => $acf]);
        $this->assertSame(1, $xpath->query('//li[.//*[@id="persian-kit-module-acf-name"]]//*[contains(@class, "persian-kit-badge")]')->length);
        $this->assertNotSame('', $xpath->evaluate('string(//*[contains(@class, "persian-kit-wrap")]/@data-seen-nonce)'));

        // Opening the Integrations tab shows it one last time.
        $_GET['tab'] = 'integrations';
        $xpath = $this->render(['acf' => $acf]);
        $this->assertSame(1, $xpath->query('//*[contains(@class, "persian-kit-badge")]')->length);
        $this->assertSame('', $xpath->evaluate('string(//*[contains(@class, "persian-kit-wrap")]/@data-seen-nonce)'));

        $xpath = $this->render(['acf' => $acf]);
        $this->assertSame(0, $xpath->query('//*[contains(@class, "persian-kit-badge")]')->length);
    }

    public function test_opening_the_woocommerce_tab_leaves_the_integrations_new(): void
    {
        $this->render([
            'woocommerce' => $this->withPlugin(WooCommerceModule::class, false),
            'acf'         => $this->withPlugin(AcfModule::class, false),
        ]);

        $_GET['tab'] = 'woocommerce';
        $active = [
            'woocommerce' => $this->withPlugin(WooCommerceModule::class, true),
            'acf'         => $this->withPlugin(AcfModule::class, true),
        ];
        $xpath = $this->render($active);
        // Still unseen: the page can report the Integrations tab later.
        $this->assertNotSame('', $xpath->evaluate('string(//*[contains(@class, "persian-kit-wrap")]/@data-seen-nonce)'));

        $_GET['tab'] = 'integrations';
        $xpath = $this->render($active);
        $this->assertSame(1, $xpath->query('//li[.//*[@id="persian-kit-module-acf-name"]]//*[contains(@class, "persian-kit-badge")]')->length);
    }

    public function test_with_no_supported_plugin_the_tab_says_so(): void
    {
        $xpath = $this->render([
            'woocommerce' => $this->withPlugin(WooCommerceModule::class, false),
            'cf7'         => $this->withPlugin(Cf7Module::class, false),
            'acf'         => $this->withPlugin(AcfModule::class, false),
        ]);

        $this->assertSame(
            'None of the supported plugins are active on this site.',
            trim($xpath->evaluate('string(//*[@id="persian-kit-panel-integrations"]//*[contains(@class, "persian-kit-empty__title")])'))
        );
        $this->assertSame(3, $xpath->query('//*[@id="persian-kit-group-also"]/following-sibling::ul/li')->length);
    }

    public function test_turning_off_an_integration_warns_about_the_forms_that_use_it(): void
    {
        $settings = $this->settings();
        $cf7 = new class ($settings) extends Cf7Module {
            public function requiredPlugins(): array
            {
                return [['name' => 'Contact Form 7', 'check' => fn () => true]];
            }

            public function formsUsingFields(): array
            {
                return [
                    ['title' => 'Contact', 'url' => 'https://example.org/edit/1'],
                    ['title' => 'Order <b>', 'url' => 'https://example.org/edit/2'],
                ];
            }
        };

        $xpath = $this->render(['cf7' => $cf7]);

        $warning = $xpath->query('//*[@id="persian-kit-module-cf7-warning"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $warning);
        $this->assertSame('!enabled', $warning->getAttribute('x-show'));
        $this->assertSame(
            '2 forms use Persian Kit fields: Contact and Order <b>. While this is off, those fields accept any text.',
            preg_replace('/\s+/', ' ', trim($warning->textContent))
        );
        $this->assertSame(['https://example.org/edit/1', 'https://example.org/edit/2'], array_map(
            static fn (DOMElement $link): string => $link->getAttribute('href'),
            iterator_to_array($xpath->query('.//a', $warning))
        ));
        // Read with the toggle once it is off.
        $this->assertStringContainsString(
            'persian-kit-module-cf7-warning',
            $xpath->evaluate('string(//input[@type="checkbox"][@name="persian_kit_settings[cf7][enabled]"]/@x-bind:aria-describedby)')
            . $xpath->evaluate('string(//input[@type="checkbox"][@name="persian_kit_settings[cf7][enabled]"]/@*[name()=":aria-describedby"])')
        );
    }

    public function test_tools_tab_holds_the_fix_tool_outside_the_form(): void
    {
        $_GET['tab'] = 'tools';
        $xpath = $this->render();

        $tool = $xpath->query('//*[@id="persian-kit-panel-tools"]//*[starts-with(@x-data, "persianKitNormalize")]');
        $this->assertSame(1, $tool->length);
        $this->assertSame(0, $xpath->query('//form//*[starts-with(@x-data, "persianKitNormalize")]')->length);

        // Nothing to save on this tab.
        $this->assertSame(1, $xpath->query('//*[contains(@class, "persian-kit-savebar")][@hidden]')->length);
    }

    public function test_save_returns_to_the_tab_it_was_sent_from(): void
    {
        $_GET['tab'] = 'writing';
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=persian-kit&tab=writing';
        $xpath = $this->render();

        $this->assertSame(
            '/wp-admin/admin.php?page=persian-kit&tab=writing',
            $xpath->evaluate('string(//form[@id="persian-kit-settings-form"]//input[@name="_wp_http_referer"]/@value)')
        );
    }

    public function test_header_shows_the_version_and_help_links_above_the_notices(): void
    {
        $xpath = $this->render();

        $this->assertSame('Persian Kit', trim($xpath->evaluate('string(//h1)')));
        $this->assertStringContainsString(
            PERSIAN_KIT_VERSION,
            $xpath->evaluate('string(//*[contains(@class, "persian-kit-header")]//*[contains(@class, "persian-kit-version")])')
        );
        $this->assertSame(3, $xpath->query('//*[contains(@class, "persian-kit-links")]//a[@target="_blank"]')->length);
        // WordPress moves admin notices after .wp-header-end, so they land below the header and tabs.
        $this->assertSame(1, $xpath->query('//*[@role="tablist"]/ancestor::*[contains(@class, "persian-kit-header")]/following-sibling::hr[contains(@class, "wp-header-end")]')->length);
    }

    public function test_every_card_and_tab_has_an_icon(): void
    {
        $xpath = $this->render();

        $cards = '//*[contains(concat(" ", normalize-space(@class), " "), " persian-kit-module ") or contains(concat(" ", normalize-space(@class), " "), " persian-kit-integration ")]';
        $this->assertSame(
            $xpath->query($cards)->length,
            $xpath->query($cards . '//*[contains(@class, "persian-kit-module__icon")][svg or span]')->length
        );
        $this->assertSame($xpath->query('//*[@role="tab"]')->length, $xpath->query('//*[@role="tab"]/svg')->length);
    }

    public function test_footer_sits_outside_the_form_and_replaces_the_wordpress_footer(): void
    {
        $xpath = $this->render();

        $this->assertSame(1, $xpath->query('//footer[contains(@class, "persian-kit-footer")]//a[@href="' . AdminPage::REPO_URL . '"]')->length);
        $this->assertSame(0, $xpath->query('//form//footer')->length);

        $this->assertNotSame('', apply_filters('admin_footer_text', 'Thank you'));
        $this->adminPage()->hideWordPressFooter();
        $this->assertSame('', apply_filters('admin_footer_text', 'Thank you'));
        $this->assertSame('', apply_filters('update_footer', 'Version 7.1'));
    }

    public function test_menu_icon_is_a_single_colour_svg(): void
    {
        $icon = \PersianKit\Components\Icon::menuIcon();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $icon);

        $svg = base64_decode(substr($icon, strlen('data:image/svg+xml;base64,')), true);
        // WordPress repaints fill="…" to match the admin colour scheme.
        $this->assertSame(1, preg_match_all('/fill="[^"]*"/', (string) $svg));
    }

    private function adminPage(): AdminPage
    {
        $settings = new SettingsManager();

        return new AdminPage($settings, [], new ConflictDetector($settings));
    }

    private function settings(): SettingsManager
    {
        $settings = new SettingsManager();
        foreach (ModuleRegistry::MODULES as $moduleClass) {
            $settings->registerDefaults($moduleClass::key(), $moduleClass::defaults());
        }

        return $settings;
    }

    /**
     * The module, with its plugin active or not whatever this site has.
     *
     * @param class-string<ModuleInterface> $moduleClass
     */
    private function withPlugin(string $moduleClass, bool $active): ModuleInterface
    {
        $settings = $this->settings();

        $module = match ($moduleClass) {
            WooCommerceModule::class => new class ($settings) extends WooCommerceModule {
                use FakesPluginState;
            },
            Cf7Module::class => new class ($settings) extends Cf7Module {
                use FakesPluginState;
            },
            AcfModule::class => new class ($settings) extends AcfModule {
                use FakesPluginState;
            },
        };

        return $module->withPluginActive($active);
    }

    /**
     * @param array<string, ModuleInterface> $replacements Modules to use in place of the real ones, by key.
     */
    private function render(array $replacements = []): DOMXPath
    {
        $settings = $this->settings();
        $modules = [];
        foreach (ModuleRegistry::MODULES as $moduleClass) {
            $modules[] = $replacements[$moduleClass::key()] ?? new $moduleClass($settings);
        }

        ob_start();
        (new AdminPage($settings, $modules, new ConflictDetector($settings)))->render();
        $html = (string) ob_get_clean();

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function module(string $key): ModuleInterface
    {
        foreach (ModuleRegistry::MODULES as $moduleClass) {
            if ($moduleClass::key() === $key) {
                return new $moduleClass(new SettingsManager());
            }
        }

        $this->fail("No module $key");
    }

    /**
     * @return array{string, string, string, bool}
     */
    private function describe(DOMXPath $xpath, DOMElement $field): array
    {
        if ($field->nodeName === 'select') {
            return [
                $field->getAttribute('name'),
                'select',
                (string) $xpath->evaluate('string(.//option[@selected]/@value)', $field),
                false,
            ];
        }

        return [
            $field->getAttribute('name'),
            $field->getAttribute('type'),
            $field->getAttribute('value'),
            $field->hasAttribute('checked'),
        ];
    }

    /**
     * @return list<string>
     */
    private function selectedTabs(DOMXPath $xpath): array
    {
        $names = [];
        foreach ($xpath->query('//*[@role="tab"][@aria-selected="true"]') as $tab) {
            $this->assertInstanceOf(DOMElement::class, $tab);
            $names[] = $tab->getAttribute('data-tab');
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function visiblePanels(DOMXPath $xpath): array
    {
        $names = [];
        foreach ($xpath->query('//*[@role="tabpanel"][not(@hidden)]') as $panel) {
            $this->assertInstanceOf(DOMElement::class, $panel);
            $names[] = substr($panel->getAttribute('id'), strlen('persian-kit-panel-'));
        }

        return $names;
    }
}
