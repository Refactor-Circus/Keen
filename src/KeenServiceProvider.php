<?php

declare(strict_types=1);

namespace RefactorCircus\Keen;

use RefactorCircus\Keen\Atrium\HistoryExtensions;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Domains\DomainServiceProvider;
use RefactorCircus\Keen\Mcp\KeenServer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Support\PackageServiceProvider;

class KeenServiceProvider extends PackageServiceProvider
{
    protected function definition(): Package
    {
        return Package::make('keen', __NAMESPACE__)->label('Keen')->server(KeenServer::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/keen.php', 'keen');

        $this->registerPackage();

        $this->app->singleton(Keen::class);

        $this->app->register(DomainServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerNotFoundResponses();
        $this->registerMcpServer();
        $this->registerCortex();
        $this->registerAtriumPlugin(KeenPlugin::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'keen');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'keen');

        // After the translations: the section and tab labels are Keen's strings.
        $this->registerHistoryExtensions();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/keen.php' => config_path('keen.php'),
        ], ['keen', 'keen-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/keen'),
        ], ['keen', 'keen-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/keen'),
        ], ['keen', 'keen-lang']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['keen', 'keen-migrations']);
    }

    /**
     * Keen's History section and Activity tabs on other packages' Atrium
     * screens, behind the same guard as the plugin: Atrium installed and
     * `keen.ui.enabled` true. Atrium is checked by name, and HistoryExtensions
     * is only loaded past the guard, so neither loads without Atrium.
     */
    private function registerHistoryExtensions(): void
    {
        if (! class_exists('RefactorCircus\\Atrium\\Atrium') || $this->config()->get($this->package()->configKey('ui.enabled')) !== true) {
            return;
        }

        HistoryExtensions::registerIfSupported();
    }
}
