<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\JalaliPeriod;
use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\ChecklistItem;
use PersianKit\Service\Import\HasReportTips;
use PersianKit\Service\Import\HasReviewNotes;
use PersianKit\Service\Import\Woo\PaymentGateways;

defined('ABSPATH') || exit;

/**
 * Parsi Date (wp-parsidate): 6.x keeps its settings in wp_parsidate and
 * wp_parsidate_{woocommerce,acf,edd}; 5.x kept them all in wpp_settings.
 */
class ParsiDateSource extends AbstractSource implements HasReviewNotes, HasReportTips
{
    public const KEY = 'wp-parsidate';

    /** Widget options of its archive and calendar widgets, in 6.4, 6.0 to 6.3 and 5.x. */
    public const WIDGET_OPTIONS = [
        'widget_wp_parsidate_archive',
        'widget_wp_parsidate_calendar',
        'widget_wpparsidate\widget\parsidatearchivewidget',
        'widget_wpparsidate\widget\parsidatecalendarwidget',
        'widget_parsidate_archive',
        'widget_parsidate_calendar',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Parsi Date', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'wp-parsidate/wp-parsidate.php';
    }

    public function hasData(): bool
    {
        if (self::arrayOption('wp_parsidate') !== [] || self::arrayOption('wpp_settings') !== []) {
            return true;
        }

        foreach (self::WIDGET_OPTIONS as $option) {
            if (array_filter(self::arrayOption($option), 'is_array') !== []) {
                return true;
            }
        }

        return false;
    }

    public function settingRows(SettingsManager $settings): array
    {
        return (new ParsiDateSettings())->rows();
    }

    /**
     * Widgets and blocks first: they show on every page. ACF values before
     * the fields, which are found by their type.
     */
    public function tasks(): array
    {
        return [new WidgetTask(), new BlockTask(), new AcfValueTask(), new AcfFieldTask(), new ThemeScanTask(ThemeScanner::PARSI_DATE, true)];
    }

    /**
     * Where its widgets are: WordPress moves them to "Inactive widgets"
     * once it is inactive and someone opens the Widgets screen.
     */
    public function snapshot(): array
    {
        $sidebars = get_option('sidebars_widgets', []);

        return ['sidebars_widgets' => is_array($sidebars) ? $sidebars : []];
    }

    public function checklist(array $snapshot): array
    {
        $items = ThemeCalls::items($this->name(), ThemeScanner::PARSI_DATE, $snapshot, true);
        $gateways = (new ParsiDateSettings())->gateways();

        if ($gateways !== []) {
            $items[] = new ChecklistItem(
                'gateways',
                __('Bank gateways that will stop', 'persian-kit'),
                __('Parsi Date\'s bank gateways stop when it is deactivated; Persian Kit has none. Set up another gateway first.', 'persian-kit'),
                PaymentGateways::entries(array_combine($gateways, array_map('ucfirst', $gateways))),
                false,
                true,
                ['acknowledge_label' => __('I have another gateway for these payments', 'persian-kit')]
            );
        }

        $scanner = new ThemeScanner();
        $templates = $scanner->blockTemplates();
        if ($templates !== []) {
            $items[] = new ChecklistItem(
                'block_templates',
                __('Parsi Date blocks in your theme\'s template files', 'persian-kit'),
                __('Blocks in the theme\'s own files can\'t be rewritten. After the switch, open each template in the Site Editor, replace them with the Archives or Calendar block and save.', 'persian-kit'),
                array_map(static fn (string $file): array => ['label' => $file], $templates),
                false,
                true
            );
        }

        $pages = ThemeScanner::elementorPages();
        if ($pages !== []) {
            $items[] = new ChecklistItem(
                'elementor',
                __('Parsi Date widgets in Elementor pages', 'persian-kit'),
                __('Replace them with WordPress\'s Archives or Calendar widget in Elementor.', 'persian-kit'),
                array_map(static fn (int $id): array => ['label' => get_the_title($id), 'url' => (string) get_edit_post_link($id, 'raw')], $pages),
                false,
                true
            );
        }

        return $items;
    }

    public function reportTips(array $job): array
    {
        $tips = [__('Clear your page cache, if the site has one: widgets and blocks changed.', 'persian-kit')];

        if ((new ParsiDateSettings())->on('core', 'conv_arabic')) {
            $tips[] = __('Parsi Date showed Arabic ي and ك as Persian without changing them. To fix the posts themselves, use "Fix letters in existing posts" on this tab.', 'persian-kit');
        }

        return $tips;
    }

    /**
     * What happens to old post links.
     */
    public function reviewNotes(array $snapshot): array
    {
        $structure = (string) get_option('permalink_structure');
        $hasDate = str_contains($structure, '%year%') || str_contains($structure, '%monthnum%') || str_contains($structure, '%day%');

        if (!$hasDate) {
            return [[
                'key'   => 'links',
                'title' => __('Links', 'persian-kit'),
                'text'  => __('Post links have no date in them, so there are no date links to keep.', 'persian-kit'),
            ]];
        }

        $count = (int) wp_count_posts('post')->publish;
        $note = [
            'key'   => 'links',
            'title' => __('Links', 'persian-kit'),
            'text'  => sprintf(
                /* translators: %s: number of posts. */
                _n(
                    '%s post with a Jalali link keeps it working, and Jalali archive links too. Links in the other calendar redirect to them.',
                    '%s posts with Jalali links keep them working, and Jalali archive links too. Links in the other calendar redirect to them.',
                    $count,
                    'persian-kit'
                ),
                number_format_i18n($count)
            ),
        ];

        $latest = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1]);
        if ($latest !== []) {
            $jalali = self::jalaliPath($latest[0], $structure);
            if ($jalali !== null) {
                $note['example'] = ['before' => $jalali, 'after' => $jalali];
            }
        }

        return [$note];
    }

    /**
     * The path Parsi Date gave a post: the structure with the Jalali date
     * of its local post_date.
     */
    private static function jalaliPath(\WP_Post $post, string $structure): ?string
    {
        if (!preg_match('/^(?!0000)\d{4}-\d{2}-\d{2}/', $post->post_date)) {
            return null;
        }

        $jalali = JalaliPeriod::fromGregorian(new \DateTimeImmutable(substr($post->post_date, 0, 10) . ' 12:00:00', wp_timezone()));
        $path = str_replace(
            ['%year%', '%monthnum%', '%day%', '%postname%', '%post_id%'],
            [(string) $jalali['jy'], sprintf('%02d', $jalali['jm']), sprintf('%02d', $jalali['jd']), rawurldecode($post->post_name), (string) $post->ID],
            $structure
        );

        return preg_match('/%[a-z_]+%/', $path) ? null : $path;
    }

    protected function isLoaded(): bool
    {
        return defined('WP_PARSI_ROOT');
    }
}
