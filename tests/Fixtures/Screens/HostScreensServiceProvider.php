<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Screens;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/**
 * Other packages' screens, declared the way a host declares them, so tests
 * can see what Keen attaches to screens it knows nothing about:
 *
 * - `keen-test.detail` (`/keen-test/detail/{screen}/{record?}`): a detail
 *   screen under any key, whose record is that product (none without one,
 *   an unsaved product with `?unsaved=1`)
 * - `keen-test.list` (`/keen-test/list/{screen}`): a list screen under any key
 *
 * Each authorizes first, as a host does: while the `keen-test.view` ability
 * is defined, it must allow the screen's record. Each renders the tab bar,
 * then the open registered tab's panel, or `before`, the page and `after`.
 *
 * Register it in a test: `app()->register(HostScreensServiceProvider::class)`.
 */
final class HostScreensServiceProvider extends ServiceProvider
{
    public const string ABILITY = 'keen-test.view';

    private const string TEMPLATE = <<<'BLADE'
        <main data-testid="host-screen">
            <x-atrium::screen-tabs :screen="$resolved" />

            @if ($resolved->openRegisteredTab !== null)
                <x-atrium::screen-tab-panel :screen="$resolved" />
            @else
                <div data-testid="host-before"><x-atrium::screen-sections :screen="$resolved" slot="before" /></div>
                <p data-testid="host-page">{{ $resolved->screen->key }}</p>
                <div data-testid="host-after"><x-atrium::screen-sections :screen="$resolved" slot="after" /></div>
                <span data-testid="host-end"></span>
            @endif
        </main>
        BLADE;

    public function boot(): void
    {
        Route::middleware('web')->prefix('keen-test')->name('keen-test.')->group(function (): void {
            Route::get('detail/{screen}/{record?}', fn (Request $request, string $screen, ?string $record = null): string => self::render(
                $request,
                Screen::standard($screen, Screen::DETAIL)->record(self::record($request, $record)),
            ))->name('detail');

            Route::get('list/{screen}', fn (Request $request, string $screen): string => self::render(
                $request,
                Screen::standard($screen, Screen::LIST),
            ))->name('list');
        });

        Route::getRoutes()->refreshNameLookups();
    }

    private static function record(Request $request, ?string $record): ?ProductModel
    {
        if ($request->boolean('unsaved')) {
            return new ProductModel(['name' => 'Draft']);
        }

        return $record === null ? null : ProductModel::query()->findOrFail($record);
    }

    private static function render(Request $request, Screen $screen): string
    {
        if (Gate::has(self::ABILITY)) {
            abort_unless(Gate::allows(self::ABILITY, [$screen->record]), 403);
        }

        return Blade::render(self::TEMPLATE, [
            'resolved' => app(ScreenExtensions::class)->resolve($screen, $request),
        ]);
    }
}
