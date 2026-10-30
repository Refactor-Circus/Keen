<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Atrium\HistoryExtensions;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/*
 * What Keen registers on Atrium's screens from `keen.atrium.history`
 * (criterion 9): the targets after normalisation, the slot, the tabs, and
 * what a bad entry does in testing and in production.
 */

uses(RebootsWithConfig::class);

/**
 * Register Keen's extensions again, on a flushed registry, with these
 * `keen.atrium.history` values.
 *
 * @param  array<string, mixed>  $config
 */
function historyExtensionsRegister(array $config = []): void
{
    Atrium::screens()->flush();

    foreach ($config as $key => $value) {
        config()->set('keen.atrium.history.'.$key, $value);
    }

    HistoryExtensions::register();
}

/**
 * Every registered section as "{target} {slot} {key}", in registration order.
 *
 * @return list<string>
 */
function historyExtensionsSections(): array
{
    $sections = [];

    foreach (Atrium::screens()->snapshot()['sections'] as $target => $slots) {
        foreach ($slots as $slot => $entries) {
            foreach ($entries as [, $section]) {
                $sections[] = "{$target} {$slot} {$section->key}";
            }
        }
    }

    return $sections;
}

/**
 * Every registered tab as "{target} {key}", in registration order.
 *
 * @return list<string>
 */
function historyExtensionsTabs(): array
{
    $tabs = [];

    foreach (Atrium::screens()->snapshot()['tabs'] as $target => $entries) {
        foreach ($entries as [, $tab]) {
            $tabs[] = "{$target} {$tab->key}";
        }
    }

    return $tabs;
}

/**
 * Production, with reported exceptions faked.
 */
function historyExtensionsInProduction(): void
{
    config()->set('app.env', 'production');
    app()->detectEnvironment(fn (): string => 'production');

    Exceptions::fake();
}

it('registers one history section on every screen, after the page, by default', function (): void {
    historyExtensionsRegister();

    expect(historyExtensionsSections())->toBe(['* after keen.history'])
        ->and(historyExtensionsTabs())->toBe(['roster.organization keen.activity']);

    $section = Atrium::screens()->sections('*', 'after')[0];

    expect($section->key)->toBe(HistoryExtensions::SECTION)
        ->and($section->label)->toBe('History')
        ->and($section->view)->toBe('keen::ui.history-section')
        ->and($section->public)->toBeFalse();
});

it('registers the activity tab with its label, icon and view', function (): void {
    historyExtensionsRegister();

    $tab = Atrium::screens()->tabs('roster.organization')[0];

    expect($tab->key)->toBe(HistoryExtensions::TAB)
        ->and($tab->label)->toBe('Activity')
        ->and($tab->icon)->toBe('clipboard-document-list')
        ->and($tab->view)->toBe('keen::ui.activity-tab')
        ->and($tab->public)->toBeFalse();
});

it('registers only * when * is among the screens', function (): void {
    historyExtensionsRegister(['screens' => ['*', 'shop.*', 'shop.product']]);

    expect(historyExtensionsSections())->toBe(['* after keen.history']);
});

it('drops exact keys a package target covers, and duplicates', function (): void {
    historyExtensionsRegister(['screens' => ['shop.*', 'shop.product', 'other.a', 'other.a', 'shop.*']]);

    expect(historyExtensionsSections())->toBe(['shop.* after keen.history', 'other.a after keen.history']);
});

it('uses the configured slot', function (): void {
    historyExtensionsRegister(['slot' => 'aside']);

    expect(historyExtensionsSections())->toBe(['* aside keen.history']);
});

it('throws for a bad entry in testing and local, naming the key and the value', function (string $env, string $key, mixed $value, string $named): void {
    app()->detectEnvironment(fn (): string => $env);

    expect(fn () => historyExtensionsRegister([$key => $value]))
        ->toThrow(InvalidArgumentException::class, 'keen.atrium.history.'.$key)
        ->and(fn () => historyExtensionsRegister([$key => $value]))
        ->toThrow(InvalidArgumentException::class, $named);
})->with(['testing', 'local'])->with([
    'a bad screen target' => ['screens', ['Bad Target'], 'Bad Target'],
    'a bad tab target' => ['tabs', ['Bad Tab' => ['scope' => true]], 'Bad Tab'],
    'a bad slot' => ['slot', 'Bad Slot', 'Bad Slot'],
]);

it('reports a bad screen target once in production, and registers the rest', function (): void {
    historyExtensionsInProduction();

    historyExtensionsRegister(['screens' => ['Bad Target', 'shop.*']]);

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'keen.atrium.history.screens')
        && str_contains($e->getMessage(), 'Bad Target'));

    expect(historyExtensionsSections())->toBe(['shop.* after keen.history'])
        ->and(historyExtensionsTabs())->toBe(['roster.organization keen.activity']);
});

it('reports a bad slot once in production, and registers no section but still the tabs', function (): void {
    historyExtensionsInProduction();

    historyExtensionsRegister(['slot' => 'Bad Slot']);

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'keen.atrium.history.slot')
        && str_contains($e->getMessage(), 'Bad Slot'));

    expect(historyExtensionsSections())->toBe([])
        ->and(historyExtensionsTabs())->toBe(['roster.organization keen.activity']);
});

it('reports a bad tab target once in production, and registers the other tabs', function (): void {
    historyExtensionsInProduction();

    historyExtensionsRegister(['tabs' => ['Bad Tab' => ['scope' => true], 'shop.product' => ['scope' => false]]]);

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidArgumentException $e): bool => str_contains($e->getMessage(), 'keen.atrium.history.tabs')
        && str_contains($e->getMessage(), 'Bad Tab'));

    expect(historyExtensionsTabs())->toBe(['shop.product keen.activity'])
        ->and(historyExtensionsSections())->toBe(['* after keen.history']);
});

it('tolerates malformed shapes', function (array $config, array $sections, array $tabs): void {
    historyExtensionsRegister($config);

    expect(historyExtensionsSections())->toBe($sections)
        ->and(historyExtensionsTabs())->toBe($tabs);
})->with([
    'screens as a string' => [['screens' => 'shop.*'], ['shop.* after keen.history'], ['roster.organization keen.activity']],
    'screens with non-strings' => [['screens' => [5, null, ['x'], 'shop.*']], ['shop.* after keen.history'], ['roster.organization keen.activity']],
    'screens as null' => [['screens' => null], [], ['roster.organization keen.activity']],
    'a slot that is not a string' => [['slot' => ['after']], ['* after keen.history'], ['roster.organization keen.activity']],
    'tabs that are not an array' => [['tabs' => 'roster.organization'], ['* after keen.history'], []],
    'a tab entry that is not an array' => [['tabs' => ['shop.product' => 'yes']], ['* after keen.history'], ['shop.product keen.activity']],
    'scope as a string' => [['tabs' => ['shop.product' => ['scope' => '1']]], ['* after keen.history'], ['shop.product keen.activity']],
]);

it('casts scope to bool: scoped to the record, or about the record', function (mixed $scope, bool $scoped): void {
    historyExtensionsRegister(['tabs' => ['shop.product' => ['scope' => $scope]]]);

    $product = ProductModel::query()->create(['name' => 'Cog']);
    $screen = Screen::standard('shop.product', Screen::DETAIL)->record($product);

    expect(Atrium::screens()->tabs('shop.product')[0]->resolveData($product, $screen))->toBe([
        'source' => 'shop',
        'scope' => $scoped ? $product : null,
        'subject' => $scoped ? null : $product,
    ]);
})->with([
    'true' => [true, true],
    "'1'" => ['1', true],
    'false' => [false, false],
    '0' => [0, false],
]);

it('resolves the screen record as the subject on a detail screen, and none on a list screen', function (): void {
    historyExtensionsRegister();

    $product = ProductModel::query()->create(['name' => 'Cog']);
    $section = Atrium::screens()->sections('*', 'after')[0];

    expect($section->resolveData($product, Screen::standard('shop.product', Screen::DETAIL)->record($product)))
        ->toBe(['source' => 'shop', 'subject' => $product])
        ->and($section->resolveData(null, Screen::standard('shop.products', Screen::LIST)))
        ->toBe(['source' => 'shop', 'subject' => null]);
});

it('throws a bad target from the provider at boot in testing', function (): void {
    $thrown = null;

    try {
        $this->rebootWith(['keen.atrium.history.screens' => ['Bad Target']]);
    } catch (InvalidArgumentException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(InvalidArgumentException::class)
        ->and($thrown?->getMessage())->toContain('keen.atrium.history.screens')->toContain('Bad Target')
        ->and($thrown?->getTraceAsString())->toContain('KeenServiceProvider->boot()');
});
