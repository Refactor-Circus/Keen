<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests;

use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RefactorCircus\Atrium\AtriumServiceProvider;
use RefactorCircus\Keen\KeenServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Shop\ShopServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            AtriumServiceProvider::class,
            KeenServiceProvider::class,
            ShopServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('shop', ['routes' => ['enabled' => true, 'prefix' => 'shop', 'middleware' => ['api']]]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__).'/vendor/refactor-circus/atrium/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
