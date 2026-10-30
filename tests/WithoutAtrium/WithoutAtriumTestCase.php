<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\WithoutAtrium;

use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RefactorCircus\Keen\KeenServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Shop\ShopServiceProvider;

/**
 * Keen booted as an application without Atrium installs it: no Atrium
 * provider, no Atrium migrations, and (through `bootstrap.php`) no Atrium
 * class the autoloader can find.
 */
abstract class WithoutAtriumTestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
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
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__).'/Fixtures/migrations');
    }
}
