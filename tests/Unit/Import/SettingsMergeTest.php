<?php

namespace PersianKit\Tests\Unit\Import;

use PersianKit\Service\Import\Settings\SettingsImporter;
use PHPUnit\Framework\TestCase;

/**
 * The merge rules: an import only turns settings on, sets a single value
 * only over Persian Kit's default, and merges province lists, the same in
 * any order.
 */
class SettingsMergeTest extends TestCase
{
    private const DEFAULTS = [
        'digit_conversion' => ['enabled' => false, 'prices' => true],
        'admin_font'       => ['enabled' => true, 'font' => 'vazirmatn'],
        'woocommerce'      => ['enabled' => true, 'allowed_states' => []],
    ];

    public function test_booleans_only_turn_on(): void
    {
        $this->assertTrue(SettingsImporter::merge(false, false, true));
        $this->assertTrue(SettingsImporter::merge(true, false, false));
        $this->assertFalse(SettingsImporter::merge(false, true, false));
    }

    public function test_a_single_value_is_set_only_over_the_default(): void
    {
        $this->assertSame('noto-sans-arabic', SettingsImporter::merge('vazirmatn', 'vazirmatn', 'noto-sans-arabic'));
        $this->assertSame('ibm-plex-sans-arabic', SettingsImporter::merge('ibm-plex-sans-arabic', 'vazirmatn', 'noto-sans-arabic'));
        $this->assertSame('vazirmatn', SettingsImporter::merge(null, 'vazirmatn', 'vazirmatn'));
    }

    public function test_province_lists(): void
    {
        // Every province allowed: take the source's list.
        $this->assertSame(['ESF', 'THR'], SettingsImporter::merge([], [], ['THR', 'ESF']));
        // Already a list: both, once each.
        $this->assertSame(['ESF', 'FRS', 'THR'], SettingsImporter::merge(['THR', 'FRS'], [], ['ESF', 'THR']));
        // A source that allowed every province changes nothing.
        $this->assertSame(['THR'], SettingsImporter::merge(['THR'], [], []));
    }

    public function test_the_result_is_the_same_in_any_order(): void
    {
        $current = [
            'digit_conversion' => ['enabled' => false, 'prices' => false],
            'admin_font'       => ['enabled' => true, 'font' => 'vazirmatn'],
            'woocommerce'      => ['enabled' => true, 'allowed_states' => []],
        ];
        $parsiDate = ['digit_conversion.enabled' => true, 'admin_font.font' => 'vazirmatn', 'woocommerce.allowed_states' => ['THR']];
        $persianWoo = ['digit_conversion.prices' => true, 'woocommerce.allowed_states' => ['ESF', 'THR']];

        $one = SettingsImporter::mergeAll(SettingsImporter::mergeAll($current, self::DEFAULTS, $parsiDate), self::DEFAULTS, $persianWoo);
        $two = SettingsImporter::mergeAll(SettingsImporter::mergeAll($current, self::DEFAULTS, $persianWoo), self::DEFAULTS, $parsiDate);

        $this->assertSame($one, $two);
        $this->assertSame(['ESF', 'THR'], $one['woocommerce']['allowed_states']);
        $this->assertTrue($one['digit_conversion']['enabled']);
        $this->assertTrue($one['digit_conversion']['prices']);
    }

    public function test_diff_lists_only_what_changed(): void
    {
        $before = ['digit_conversion' => ['enabled' => false, 'prices' => true]];
        $after = ['digit_conversion' => ['enabled' => true, 'prices' => true]];

        $this->assertSame(['digit_conversion.enabled' => ['old' => false, 'new' => true]], SettingsImporter::diff($before, $after));
    }

    public function test_split_reads_a_bare_module_as_its_enabled_flag(): void
    {
        $this->assertSame(['woocommerce', 'city_select'], SettingsImporter::split('woocommerce.city_select'));
        $this->assertSame(['woocommerce', 'enabled'], SettingsImporter::split('woocommerce'));
    }
}
