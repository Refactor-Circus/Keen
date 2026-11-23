<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\ServiceProvider;

/*
 * Keen v2 is a JSON API plus MCP tools: the Atrium plugin, its screens,
 * Blade views, translations and config are gone.
 */

uses(RebootsWithConfig::class);

/**
 * The names of the registered routes starting with `$prefix`, sorted.
 *
 * @return list<string>
 */
function routeNamesStartingWith(string $prefix): array
{
    $names = array_values(array_filter(
        array_map(fn (Route $route): ?string => $route->getName(), Router::getRoutes()->getRoutes()),
        fn (?string $name): bool => $name !== null && str_starts_with($name, $prefix),
    ));

    sort($names);

    return $names;
}

/**
 * The registered routes answering `$method` at `$uri`.
 *
 * @return list<Route>
 */
function routesAt(string $method, string $uri): array
{
    return array_values(array_filter(
        Router::getRoutes()->getRoutes(),
        fn (Route $route): bool => $route->uri() === $uri && in_array($method, $route->methods(), true),
    ));
}

it('names Atrium nowhere in the source or the config', function (): void {
    $root = dirname(__DIR__, 2);
    $files = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)));
    $paths = [...array_map(fn (SplFileInfo $file): string => $file->getPathname(), array_values($files)), $root.'/config/keen.php'];

    $naming = array_values(array_filter($paths, fn (string $path): bool => str_contains((string) file_get_contents($path), 'Atrium')));

    expect($paths)->not->toBeEmpty()
        ->and($naming)->toBe([]);
});

it('has no src/Atrium, resources/views or lang folder', function (): void {
    $root = dirname(__DIR__, 2);

    expect($root.'/src/Atrium')->not->toBeDirectory()
        ->and($root.'/resources/views')->not->toBeDirectory()
        ->and($root.'/lang')->not->toBeDirectory();
});

it('registers no keen view or translation namespace', function (): void {
    expect(app('view')->getFinder()->getHints())->not->toHaveKey('keen')
        ->and(app('translator')->getLoader()->namespaces())->not->toHaveKey('keen');
});

it('publishes no views and no translations', function (): void {
    expect(ServiceProvider::publishableGroups())
        ->toContain('keen-config')
        ->toContain('keen-migrations')
        ->not->toContain('keen-views')
        ->not->toContain('keen-lang');
});

it('names only the json api routes keen.*, and no atrium.keen.* route', function (): void {
    expect(routeNamesStartingWith('keen.'))->toBe([
        'keen.entries.index',
        'keen.entries.show',
        'keen.entries.store',
        'keen.packages.index',
    ])
        ->and(routeNamesStartingWith('atrium.'))->toBe([])
        ->and(routesAt('POST', 'mcp/keen'))->toBe([]);
});

it('adds only the mcp route when keen.mcp.web.enabled is on', function (): void {
    $this->rebootWith(['keen.mcp.web.enabled' => true]);

    expect(routesAt('POST', 'mcp/keen'))->toHaveCount(1)
        ->and(routeNamesStartingWith('keen.'))->toBe([
            'keen.entries.index',
            'keen.entries.show',
            'keen.entries.store',
            'keen.packages.index',
        ])
        ->and(routeNamesStartingWith('atrium.'))->toBe([]);
});

it('has no ui or atrium config, and keeps every other key', function (): void {
    expect(config('keen.ui'))->toBeNull()
        ->and(config('keen.atrium'))->toBeNull()
        ->and(array_keys((array) config('keen')))->toBe([
            'enabled',
            'retention_days',
            'redact',
            'ignore',
            'tables',
            'authorization',
            'policies',
            'routes',
            'mcp',
            'cortex',
        ]);
});
