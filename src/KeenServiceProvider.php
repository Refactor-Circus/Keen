<?php

declare(strict_types=1);

namespace JayI\Keen;

use JayI\Foundation\Packages\Package;
use JayI\Foundation\Support\PackageServiceProvider;
use JayI\Keen\Atrium\KeenPlugin;
use JayI\Keen\Domains\DomainServiceProvider;
use JayI\Keen\Mcp\KeenServer;

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
        $this->registerMcpServer();
        $this->registerCortex();
        $this->registerAtriumPlugin(KeenPlugin::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'keen');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'keen');

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
}
