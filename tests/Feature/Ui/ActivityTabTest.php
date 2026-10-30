<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Atrium\HistoryExtensions;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Screens\HostScreensServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/*
 * The Activity tab Keen adds to the screens `keen.atrium.history.tabs` names
 * (criterion 12). The shipped config names `roster.organization`; here a
 * `roster` package is registered in the test and products stand in for its
 * organizations. Keen's code names neither.
 */

beforeEach(function (): void {
    app()->register(HostScreensServiceProvider::class);

    $this->roster = app(PackageRegistry::class)->register(Package::make('roster', 'Vendor\\Roster')->label('Roster'));

    $this->acme = ProductModel::query()->create(['name' => 'Acme']);
    $this->globex = ProductModel::query()->create(['name' => 'Globex']);

    $this->actingAs(user());
});

/**
 * An organization's screen, optionally with a tab open.
 */
function activityTabUrl(ProductModel $organization, ?string $tab = null): string
{
    return route('keen-test.detail', array_filter(['screen' => 'roster.organization', 'record' => $organization->id, 'tab' => $tab]));
}

it('shows an activity tab whose panel lists the entries recorded in the organization', function (): void {
    Keen::record('acme.renamed')->source('roster')->in($this->acme)->save();
    Keen::record('acme.member-added')->source('roster')->in($this->acme)->on($this->globex)->save();
    Keen::record('globex.renamed')->source('roster')->in($this->globex)->save();

    $this->get(activityTabUrl($this->acme))
        ->assertOk()
        ->assertSee('data-testid="tab-keen.activity"', false)
        ->assertSee('Activity')
        ->assertDontSee('data-testid="tab-panel-keen.activity"', false);

    app()->forgetScopedInstances();

    $this->get(activityTabUrl($this->acme, HistoryExtensions::TAB))
        ->assertOk()
        ->assertSeeInOrder(['data-testid="tab-panel-keen.activity"', 'data-testid="audit-trail"'], false)
        ->assertSee('acme.renamed')
        ->assertSee('acme.member-added')
        ->assertDontSee('globex.renamed')
        // The panel replaces the page, and the history section with it.
        ->assertDontSee('data-testid="host-page"', false);
});

it('hides the tab from those the history rule refuses, and answers 403 when it is asked for', function (): void {
    Keen::record('acme.renamed')->source('roster')->in($this->acme)->save();

    $this->roster->authorizeHistory(fn (): bool => false);

    $this->get(activityTabUrl($this->acme))
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee('Activity')
        ->assertDontSee('data-testid="section-keen.history"', false);

    app()->forgetScopedInstances();

    $this->get(activityTabUrl($this->acme, HistoryExtensions::TAB))
        ->assertForbidden()
        ->assertDontSee('acme.renamed');
});

it('relies on the host to keep one organization from another\'s screen', function (): void {
    Keen::record('acme.renamed')->source('roster')->in($this->acme)->save();
    Keen::record('globex.renamed')->source('roster')->in($this->globex)->save();

    // The viewer may read roster's history, but the host lets them see Acme only.
    Gate::define(HostScreensServiceProvider::ABILITY, fn (User $user, ?ProductModel $organization = null): bool => $organization?->is($this->acme) === true);

    $this->get(activityTabUrl($this->globex, HistoryExtensions::TAB))
        ->assertForbidden()
        ->assertDontSee('globex.renamed');

    app()->forgetScopedInstances();

    $this->get(activityTabUrl($this->acme, HistoryExtensions::TAB))
        ->assertOk()
        ->assertSee('acme.renamed')
        ->assertDontSee('globex.renamed');
});

it('lists the organization\'s own history for an entry that is not scoped', function (): void {
    Atrium::screens()->flush();
    config()->set('keen.atrium.history.tabs', ['roster.organization' => ['scope' => false]]);
    HistoryExtensions::register();

    Keen::record('acme.edited')->source('roster')->on($this->acme)->save();
    Keen::record('acme.member-added')->source('roster')->in($this->acme)->on($this->globex)->save();
    Keen::record('globex.edited')->source('roster')->on($this->globex)->save();

    $this->get(activityTabUrl($this->acme, HistoryExtensions::TAB))
        ->assertOk()
        ->assertSee('data-testid="tab-panel-keen.activity"', false)
        ->assertSee('acme.edited')
        ->assertDontSee('acme.member-added')
        ->assertDontSee('globex.edited');
});

it('adds nothing to other screens', function (): void {
    Exceptions::fake();

    Keen::record('acme.renamed')->source('roster')->in($this->acme)->save();

    $this->get(route('keen-test.detail', ['screen' => 'other.screen', 'record' => $this->acme->id]))
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee('Activity')
        ->assertDontSee('data-testid="tab-keen.activity"', false)
        ->assertDontSee('data-testid="section-keen.history"', false);

    Exceptions::assertNothingReported();
});

it('registers no tab keyed activity', function (): void {
    $keys = [];

    foreach (Atrium::screens()->snapshot()['tabs'] as $entries) {
        foreach ($entries as [, $tab]) {
            $keys[] = $tab->key;
        }
    }

    expect($keys)->toContain(HistoryExtensions::TAB)->not->toContain('activity');
});
