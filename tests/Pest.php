<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Facade;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Atrium\Testing\ResetsScreenExtensions;
use RefactorCircus\Keen\Mcp\KeenServer;
use RefactorCircus\Keen\Tests\RenamedTablesTestCase;
use RefactorCircus\Keen\Tests\TestCase;
use RefactorCircus\Keystone\Testing\McpTools;
use RefactorCircus\Keystone\Testing\Parity;

// Whatever a test registers on Atrium's screens is gone after it; what the
// providers registered at boot (Keen's History section and tabs) stays.
// Pest loads this file for the without-Atrium suite too, where the trait is gone.
$resets = trait_exists(ResetsScreenExtensions::class) ? [ResetsScreenExtensions::class] : [];

uses(TestCase::class, ...$resets)->in('Feature');
uses(RenamedTablesTestCase::class, ...$resets)->in('Modes/RenamedTables');

/**
 * Lets a test boot the application again with config set before the
 * providers boot, for what Keen reads only at boot:
 *
 *     uses(RebootsWithConfig::class);
 *
 *     $this->rebootWith(['keen.ui.enabled' => false]);
 *
 * The new application gets a fresh database, migrated again, which teardown
 * rolls back. If boot throws, the test keeps its own application and database.
 */
trait RebootsWithConfig
{
    /** @var array<string, mixed> */
    private static array $bootConfig = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (self::$bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function rebootWith(array $config): void
    {
        self::$bootConfig = $config;

        try {
            $this->refreshApplication();
        } finally {
            self::$bootConfig = [];

            // An application that failed to boot leaves the container and the
            // facades pointing at it; hand them back to the test's own.
            Container::setInstance($this->app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->app);
        }

        // The old migrations ran on the old application's in-memory database,
        // which is gone: migrate the new one, so teardown rolls back only that.
        $this->cachedTestMigratorProcessors = [];
        $this->defineDatabaseMigrations();
    }
}

function user(string $name = 'Ada Lovelace'): User
{
    return User::forceCreate(['name' => $name, 'email' => str($name)->slug().'@example.com', 'password' => 'secret']);
}

/**
 * Call a Keen MCP tool as a client does, with its output schema checked.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(string $tool, array $arguments = []): TestResponse
{
    return McpTools::call(KeenServer::class, $tool, $arguments);
}

/**
 * Actions missing from the JSON API, MCP or the Atrium section.
 *
 * @return array<string, array<int, string>>
 */
function parityGaps(): array
{
    $root = dirname(__DIR__).'/src';

    return Parity::gaps($root.'/Domains/*/Actions/*.php', [
        'http' => $root.'/Domains/*/Http/Requests/*.php',
        'mcp' => $root.'/Domains/*/Mcp/Requests/*.php',
        'ui' => $root.'/Atrium/Http/Controllers/*.php',
    ]);
}
