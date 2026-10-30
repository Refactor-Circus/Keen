<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Data\TabDefinition;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\KeenServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Policies\ReadOnlyAuditPolicy;

/*
 * Extensions' `permission()`s on Keen's screens are decided by Keen's own
 * Authorizer over `keen.policies` (criterion 14): on the screen's record, on
 * a row's entry, or on AuditEntryModel itself when there is no record.
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    config()->set('keen.authorization', true);
    config()->set('keen.policies.'.AuditEntryModel::class, ReadOnlyAuditPolicy::class);

    // Register the policies again, as the provider does on boot.
    (fn () => $this->registerPolicies())->call(app()->getProvider(KeenServiceProvider::class));

    View::addNamespace('keen-probes', dirname(__DIR__, 2).'/Fixtures/Screens');

    $this->ada = user();
    $this->grace = user('Grace Hopper');
});

/**
 * A section in the slot that renders only for those granted the permission,
 * keyed `test.gated-{permission}` in kebab case.
 */
function screenAccessSection(string $screen, string $slot, string $permission): void
{
    Atrium::screens()->section($screen, $slot, SectionDefinition::make('test.gated-'.str($permission)->kebab())
        ->view('keen-probes::ProbeSection')
        ->permission($permission)
        ->resolve(fn (): array => ['probe' => $permission]));
}

/**
 * A request as the user, or as a guest.
 */
function screenAccessRequest(?User $user): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

it('shows a view section on an entry to someone the policy lets view it', function (): void {
    // Nobody reads the whole log, but people may read their own entries.
    Gate::define('viewAuditLog', fn (User $user, ?string $source = null): bool => false);

    $entry = Keen::record('note.mine')->by($this->ada)->save();

    screenAccessSection('keen.entry', 'header.actions', 'view');
    screenAccessSection('keen.entry', 'after', 'viewPersonalData');

    // The policy lets Ada view her entry, but not its personal data.
    $this->actingAs($this->ada)->get(route('atrium.keen.entries.show', $entry->id))
        ->assertOk()
        ->assertSee('data-testid="section-test.gated-view"', false)
        ->assertDontSee('data-testid="section-test.gated-view-personal-data"', false);
});

it('decides the screen record\'s permission with the policy', function (): void {
    Gate::define('viewAuditLog', fn (User $user, ?string $source = null): bool => false);

    $entry = Keen::record('note.mine')->by($this->ada)->save();
    $screen = KeenPlugin::screen('keen.entry', Screen::DETAIL)->record($entry);

    expect($screen->permits('view', screenAccessRequest($this->ada), $entry))->toBeTrue()
        ->and($screen->permits('view', screenAccessRequest($this->grace), $entry))->toBeFalse()
        ->and($screen->permits('create', screenAccessRequest($this->ada), $entry))->toBeFalse();
});

it('decides each row\'s permission on that row\'s entry', function (): void {
    // The whole log for everyone, but entries of the application only for their makers.
    Gate::define('viewAuditLog', fn (User $user, ?string $source = null): bool => $source !== 'app');

    $shop = Keen::record('product.created')->source('shop')->by($this->grace)->save();
    $app = Keen::record('note.theirs')->by($this->grace)->save();

    screenAccessSection('keen.entries', 'row.actions', 'view');

    $html = (string) $this->actingAs($this->ada)->get(route('atrium.keen.entries.index'))->assertOk()->getContent();

    expect(substr_count($html, 'data-testid="probe-view"'))->toBe(1)
        ->and($html)->toContain('data-testid="probe-view" data-record="'.$shop->id.'"')
        ->not->toContain('data-testid="probe-view" data-record="'.$app->id.'"');
});

it('asks about AuditEntryModel on a list screen, which has no record', function (string $key): void {
    $asked = [];
    Gate::before(function (User $user, string $ability, array $arguments) use (&$asked): ?bool {
        $asked[] = [$ability, $arguments[0] ?? null];

        return null;
    });

    $entry = Keen::record('note.mine')->by($this->ada)->save();
    $screen = KeenPlugin::screen($key, Screen::LIST);

    $screen->permits('viewAny', screenAccessRequest($this->ada), null);
    $screen->permits('view', screenAccessRequest($this->ada), $entry);

    expect($asked)->toBe([['viewAny', AuditEntryModel::class], ['view', $entry]]);
})->with(['keen.entries', 'keen.history']);

it('shows a viewAny section on a list screen only to those who may read the whole log', function (bool $whole): void {
    // The package's own history stays readable either way.
    Gate::define('viewAuditLog', fn (User $user, ?string $source = null): bool => $whole || $source !== null);

    screenAccessSection('keen.entries', 'header.actions', 'viewAny');
    screenAccessSection('keen.history', 'header.actions', 'viewAny');

    foreach ([route('atrium.keen.entries.index'), route('atrium.keen.history.show', 'shop')] as $url) {
        $response = $this->actingAs($this->ada)->get($url)->assertOk();

        $whole
            ? $response->assertSee('data-testid="section-test.gated-view-any"', false)
            : $response->assertDontSee('data-testid="section-test.gated-view-any"', false);

        app()->forgetScopedInstances();
    }
})->with([
    'the whole log' => [true],
    'their own entries' => [false],
]);

it('grants a guest nothing while authorization is on, and everything while it is off', function (): void {
    $entry = Keen::record('note.mine')->by($this->ada)->save();
    $guest = screenAccessRequest(null);

    $detail = KeenPlugin::screen('keen.entry', Screen::DETAIL)->record($entry);
    $list = KeenPlugin::screen('keen.entries', Screen::LIST);

    expect($detail->permits('view', $guest, $entry))->toBeFalse()
        ->and($list->permits('viewAny', $guest, null))->toBeFalse();

    config()->set('keen.authorization', false);

    expect($detail->permits('view', $guest, $entry))->toBeTrue()
        ->and($list->permits('viewAny', $guest, null))->toBeTrue();
});

it('declares Keen\'s screens with the standard slots', function (string $key, string $kind, array $slots, array $rowSlots): void {
    $screen = KeenPlugin::screen($key, $kind);

    expect($screen->key)->toBe($key)
        ->and($screen->kind)->toBe($kind)
        ->and($screen->slots)->toBe($slots)
        ->and($screen->rowSlots)->toBe($rowSlots)
        ->and(array_map(fn (TabDefinition $tab): string => $tab->key, $screen->tabs))->toBe([Screen::OVERVIEW]);
})->with([
    ['keen.entries', Screen::LIST, ['header.actions', 'before', 'after'], ['row.actions']],
    ['keen.entry', Screen::DETAIL, ['header.actions', 'before', 'after', 'aside'], []],
    ['keen.history', Screen::LIST, ['header.actions', 'before', 'after'], ['row.actions']],
]);
