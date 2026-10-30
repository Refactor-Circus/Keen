<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Keen\Atrium\HistoryExtensions;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use RefactorCircus\Keen\KeenServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductCreatedActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keen\Tests\WithoutAtrium\WithoutAtriumTestCase;

uses(WithoutAtriumTestCase::class);

it('runs with the atrium psr-4 prefix removed from the autoloader', function (): void {
    $loaders = ClassLoader::getRegisteredLoaders();

    expect($loaders)->not->toBeEmpty();

    foreach ($loaders as $loader) {
        expect(array_keys($loader->getPrefixesPsr4()))->not->toContain('RefactorCircus\\Atrium\\')
            ->and($loader->findFile('RefactorCircus\\Atrium\\Atrium'))->toBeFalse();
    }
});

it('cannot load atrium', function (): void {
    expect(class_exists('RefactorCircus\\Atrium\\Atrium'))->toBeFalse();
});

it('boots keen without loading its atrium layer', function (): void {
    expect(app()->getProvider(KeenServiceProvider::class))->not->toBeNull()
        ->and(class_exists(HistoryExtensions::class, false))->toBeFalse()
        ->and(class_exists(KeenPlugin::class, false))->toBeFalse();
});

it('registers no atrium route', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn (RoutingRoute $route): ?string => $route->getName())
        ->filter()
        ->values();

    expect($names)->toContain('keen.entries.index')
        ->and($names->filter(fn (string $name): bool => str_starts_with($name, 'atrium.'))->all())->toBe([]);
});

it('records an action and lists it over the json api', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog']);
    ProductCreatedActionEvent::dispatch($product);

    $this->getJson(route('keen.entries.index'))
        ->assertOk()
        ->assertJsonPath('data.0.action', 'product.created')
        ->assertJsonPath('data.0.source', 'shop');
});

it('answers the mcp listing', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog']);
    ProductCreatedActionEvent::dispatch($product);

    mcpTool(ListAuditEntriesTool::class)->assertOk()->assertSee('product.created');
});
