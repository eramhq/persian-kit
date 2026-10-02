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
use PersianKit\Modules\Forms\FormsModule;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

class SettingsPageTest extends WordPressIntegrationTestCase
{
    /**
     * Every settings field the page sent before it had tabs, with its value
     * and whether a checkbox starts checked, on a fresh install. The order
     * may change. [name, type, value, checked]
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
        ['persian_kit_settings[woocommerce][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[woocommerce][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[woocommerce][checkout_normalize]', 'hidden', '0', false],
        ['persian_kit_settings[woocommerce][checkout_normalize]', 'checkbox', '1', true],
        ['persian_kit_settings[woocommerce][checkout_validate]', 'hidden', '0', false],
        ['persian_kit_settings[woocommerce][checkout_validate]', 'checkbox', '1', true],
        ['persian_kit_settings[woocommerce][city_select]', 'hidden', '0', false],
        ['persian_kit_settings[woocommerce][city_select]', 'checkbox', '1', false],
        ['persian_kit_settings[woocommerce][national_id]', 'select', 'off', false],
        ['persian_kit_settings[forms][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[forms][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[forms][cf7]', 'hidden', '0', false],
        ['persian_kit_settings[forms][cf7]', 'checkbox', '1', true],
        ['persian_kit_settings[forms][acf]', 'hidden', '0', false],
        ['persian_kit_settings[forms][acf]', 'checkbox', '1', true],
        ['persian_kit_settings[utilities][enabled]', 'hidden', '0', false],
        ['persian_kit_settings[utilities][enabled]', 'checkbox', '1', true],
        ['persian_kit_settings[utilities][persian_slugs]', 'hidden', '0', false],
        ['persian_kit_settings[utilities][persian_slugs]', 'checkbox', '1', true],
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

        Bootstrap::activate(false);

        set_current_screen('toplevel_page_persian-kit');
    }

    protected function tearDown(): void
    {
        unset($_GET['tab']);
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

        $this->assertEqualsCanonicalizing(self::FIELDS, $fields);
        $this->assertCount(count(self::FIELDS), $fields);
    }

    public function test_settings_fields_sit_only_inside_the_form(): void
    {
        $xpath = $this->render();

        $this->assertSame(
            0,
            $xpath->query('//*[starts-with(@name, "persian_kit_settings")][not(ancestor::form[@id="persian-kit-settings-form"])]')->length
        );
    }

    public function test_page_has_four_tabs_and_shows_the_first_by_default(): void
    {
        $xpath = $this->render();

        $tabs = $xpath->query('//*[@role="tablist"]/*[@role="tab"]');
        $this->assertSame(AdminPage::TABS, array_map(
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

        foreach (AdminPage::GROUPS as $moduleKey => $tab) {
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

    public function test_a_card_says_which_plugins_are_not_active(): void
    {
        $settings = new SettingsManager();
        $settings->registerDefaults(FormsModule::key(), FormsModule::defaults());
        $forms = new class ($settings) extends FormsModule {
            public function inactivePlugins(): array
            {
                return ['Contact Form 7', 'ACF'];
            }
        };

        $xpath = $this->render(['forms' => $forms]);

        $note = $xpath->query('//*[@id="persian-kit-module-forms-inactive"]');
        $this->assertSame(1, $note->length);
        $this->assertSame('Not active on this site: Contact Form 7 and ACF.', trim($note->item(0)->textContent));
        $this->assertStringContainsString(
            'persian-kit-module-forms-inactive',
            $xpath->evaluate('string(//input[@type="checkbox"][@name="persian_kit_settings[forms][enabled]"]/@aria-describedby)')
        );
    }

    public function test_cards_of_active_plugins_have_no_note(): void
    {
        $xpath = $this->render();

        foreach (['woocommerce', 'forms'] as $moduleKey) {
            $module = $this->module($moduleKey);
            $this->assertSame(
                $module->inactivePlugins() === [] ? 0 : 1,
                $xpath->query(sprintf('//*[@id="persian-kit-module-%s-inactive"]', $moduleKey))->length
            );
        }
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

    /**
     * @param array<string, ModuleInterface> $replacements Modules to use in place of the real ones, by key.
     */
    private function render(array $replacements = []): DOMXPath
    {
        $settings = new SettingsManager();
        $modules = [];
        foreach (ModuleRegistry::MODULES as $moduleClass) {
            $settings->registerDefaults($moduleClass::key(), $moduleClass::defaults());
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
