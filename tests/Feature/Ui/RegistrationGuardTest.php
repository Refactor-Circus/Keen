<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Atrium\HistoryExtensions;
use RefactorCircus\Keen\Tests\Fixtures\Screens\PreRegistryAtrium;

/*
 * Keen registers its screen extensions only behind the Atrium guard
 * (criterion 2): Atrium installed, `keen.ui.enabled` true, and an Atrium
 * with the screen registry.
 */

uses(RebootsWithConfig::class);

/**
 * Every key the registry (or a snapshot of it) holds, as
 * "{target} {slot} {key}" for sections and "{target} tab {key}" for tabs.
 *
 * @param  array{tabs: array<string, array<array-key, array{int, mixed}>>, sections: array<string, array<array-key, array<array-key, array{int, mixed}>>>, sequence: int}|null  $snapshot
 * @return list<string>
 */
function registrationGuardKeys(?array $snapshot = null): array
{
    $snapshot ??= Atrium::screens()->snapshot();
    $keys = [];

    foreach ($snapshot['sections'] as $target => $slots) {
        foreach ($slots as $slot => $entries) {
            foreach ($entries as [, $section]) {
                $keys[] = "{$target} {$slot} {$section->key}";
            }
        }
    }

    foreach ($snapshot['tabs'] as $target => $entries) {
        foreach ($entries as [, $tab]) {
            $keys[] = "{$target} tab {$tab->key}";
        }
    }

    return $keys;
}

it('registers the history section and the activity tab when keen boots with atrium', function (): void {
    expect(registrationGuardKeys())->toContain('* after keen.history')
        ->toContain('roster.organization tab keen.activity')
        ->and(Route::has('atrium.keen.history.show'))->toBeTrue();
});

it('registers nothing on atrium\'s screens while the keen ui is off at boot', function (): void {
    $this->rebootWith(['keen.ui.enabled' => false]);

    // Atrium itself still booted, with its registry.
    expect(app()->bound(ScreenExtensions::class))->toBeTrue()
        ->and(array_filter(registrationGuardKeys(), fn (string $key): bool => str_contains($key, ' keen.')))->toBe([])
        ->and(Route::has('atrium.keen.history.show'))->toBeFalse()
        ->and(Route::has('atrium.keen.entries.index'))->toBeFalse();
});

it('registers nothing on an atrium from before the screen registry', function (): void {
    $before = Atrium::screens()->snapshot();

    HistoryExtensions::registerIfSupported(PreRegistryAtrium::class);

    expect(method_exists(PreRegistryAtrium::class, 'screens'))->toBeFalse()
        ->and(Atrium::screens()->snapshot()['sequence'])->toBe($before['sequence'])
        ->and(registrationGuardKeys())->toBe(registrationGuardKeys($before))
        ->and(registrationGuardKeys($before))->toContain('* after keen.history');

    // Keen's own dashboard still answers.
    app()->detectEnvironment(fn (): string => 'local');
    $this->actingAs(user());

    expect(route('atrium.keen.entries.index', absolute: false))->toBe('/atrium/keen/entries');

    $this->get(route('atrium.keen.entries.index'))->assertOk();
});

it('registers through the guard on an atrium with the screen registry', function (): void {
    Atrium::screens()->flush();

    HistoryExtensions::registerIfSupported();

    expect(registrationGuardKeys())->toBe(['* after keen.history', 'roster.organization tab keen.activity']);
});
