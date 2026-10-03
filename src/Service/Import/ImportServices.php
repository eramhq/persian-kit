<?php

namespace PersianKit\Service\Import;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\SettingsManager;
use PersianKit\Service\Import\Settings\SettingsImporter;
use PersianKit\Service\Import\Sources\ParsiDate\ParsiDateSource;
use PersianKit\Service\Import\Sources\PersianWooCommerce\PersianWooCommerceSource;
use PersianKit\Service\Import\Sources\Shipping\PwsSource;

defined('ABSPATH') || exit;

/**
 * The switch from other Persian plugins. Always on: it reads what those
 * plugins left behind, whether or not they or any Persian Kit module are
 * active.
 */
final class ImportServices
{
    public static function register(ServiceContainer $container): void
    {
        $container->register(ImportLog::class, static fn (): ImportLog => new ImportLog());
        $container->register(ImportState::class, static fn (): ImportState => new ImportState());
        $container->register(SettingsImporter::class, static fn (ServiceContainer $c): SettingsImporter => new SettingsImporter($c->get(SettingsManager::class)));

        $container->register(SourceRegistry::class, static fn (): SourceRegistry => new SourceRegistry([
            new ParsiDateSource(),
            new PersianWooCommerceSource(),
            new PwsSource(),
        ]));

        $container->register(ImportRunner::class, static fn (ServiceContainer $c): ImportRunner => new ImportRunner(
            $c->get(SourceRegistry::class),
            $c->get(SettingsManager::class),
            $c->get(SettingsImporter::class),
            $c->get(ImportLog::class),
            $c->get(ImportState::class)
        ));

        $container->register(ImportReview::class, static fn (ServiceContainer $c): ImportReview => new ImportReview(
            $c->get(SourceRegistry::class),
            $c->get(ImportRunner::class),
            $c->get(SettingsImporter::class),
            $c->get(ImportState::class),
            $c->get(ImportLog::class)
        ));

        $container->register(ImportReport::class, static fn (ServiceContainer $c): ImportReport => new ImportReport(
            $c->get(ImportLog::class),
            $c->get(ImportState::class)
        ));

        $container->register(ReportDownload::class, static fn (ServiceContainer $c): ReportDownload => new ReportDownload(
            $c->get(SourceRegistry::class),
            $c->get(ImportReport::class)
        ));

        $container->register(PluginsScreenNotice::class, static fn (ServiceContainer $c): PluginsScreenNotice => new PluginsScreenNotice(
            $c->get(SourceRegistry::class)
        ));

        $container->register(ImportRestController::class, static fn (ServiceContainer $c): ImportRestController => new ImportRestController(
            $c->get(ImportRunner::class),
            $c->get(ImportReview::class),
            $c->get(ImportReport::class)
        ));
    }

    public static function boot(ServiceContainer $container): void
    {
        add_action('rest_api_init', static function () use ($container): void {
            $container->get(ImportRestController::class)->registerRoutes();
        });

        if (is_admin()) {
            $container->get(ReportDownload::class)->register();
            $container->get(PluginsScreenNotice::class)->register();
        }
    }
}
