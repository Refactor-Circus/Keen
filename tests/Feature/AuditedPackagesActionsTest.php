<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditedPackagesAction;
use RefactorCircus\Keen\Domains\Audit\Events\AuditedPackagesListedActionEvent;
use RefactorCircus\Keen\Domains\Audit\Events\AuditedPackagesListingActionEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;
use RefactorCircus\Keystone\Contracts\ReadActionEvent;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * Another installed package, registered for one test.
 */
function auditedPackage(string $key, string $label): Package
{
    return app(PackageRegistry::class)->register(Package::make($key, 'RefactorCircus\\Keen\\Tests\\Fixtures\\'.ucfirst($key))->label($label));
}

/**
 * @return list<string>
 */
function listedPackageKeys(?Authenticatable $viewer = null): array
{
    /** @var list<Package> $packages */
    $packages = app(ListAuditedPackagesAction::class)->execute($viewer);

    return array_map(fn (Package $package): string => $package->key, $packages);
}

it('lists the audited packages as packages, sorted by label then key', function (): void {
    auditedPackage('warehouse', 'Alpha');
    auditedPackage('boutique', 'Shop');

    $packages = app(ListAuditedPackagesAction::class)->execute();

    expect($packages)->each->toBeInstanceOf(Package::class)
        ->and(array_map(fn (Package $package): array => [$package->key, $package->label], $packages))
        ->toBe([['warehouse', 'Alpha'], ['boutique', 'Shop'], ['shop', 'Shop']]);
});

it('never lists keen itself', function (): void {
    expect(app(PackageRegistry::class)->has('keen'))->toBeTrue()
        ->and(listedPackageKeys())->not->toContain('keen');
});

it('leaves out a package whose auditing is turned off', function (): void {
    auditedPackage('warehouse', 'Warehouse');
    config()->set('shop.audit.enabled', false);

    expect(listedPackageKeys())->toBe(['warehouse']);
});

it('leaves out a package whose history rule refuses the viewer and keeps the rest', function (): void {
    auditedPackage('vault', 'Vault')->authorizeHistory(fn (?Authenticatable $user): bool => $user instanceof User && $user->name === 'Grace Hopper');
    $ada = user();
    $grace = user('Grace Hopper');

    expect(listedPackageKeys($ada))->toContain('shop')->not->toContain('vault')
        ->and(listedPackageKeys($grace))->toContain('shop', 'vault');
});

it('dispatches the listing then the listed event, which carries the listed keys', function (): void {
    auditedPackage('warehouse', 'Alpha');
    $dispatched = [];

    Event::listen([AuditedPackagesListingActionEvent::class, AuditedPackagesListedActionEvent::class], function (object $event) use (&$dispatched): void {
        $dispatched[] = $event;
    });

    app(ListAuditedPackagesAction::class)->execute();

    expect(array_map(fn (object $event): string => $event::class, $dispatched))
        ->toBe([AuditedPackagesListingActionEvent::class, AuditedPackagesListedActionEvent::class])
        ->and($dispatched[0])->toBeInstanceOf(ActionStartingEvent::class)->toBeInstanceOf(ReadActionEvent::class)
        ->and($dispatched[1])->toBeInstanceOf(ActionFinishedEvent::class)->toBeInstanceOf(ReadActionEvent::class)
        ->and($dispatched[1]->keys)->toBe(['warehouse', 'shop']);
});

it('records no audit entry for a listing', function (): void {
    app(ListAuditedPackagesAction::class)->execute(user());

    expect(AuditEntryModel::query()->count())->toBe(0);
});
