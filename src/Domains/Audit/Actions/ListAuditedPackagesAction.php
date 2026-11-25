<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use RefactorCircus\Keen\Domains\Audit\Events\AuditedPackagesListedActionEvent;
use RefactorCircus\Keen\Domains\Audit\Events\AuditedPackagesListingActionEvent;
use RefactorCircus\Keystone\Actions\Action;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * The installed packages whose history the viewer may read: each one that
 * audits and whose own history rule allows the viewer, sorted by label then
 * key. Keen itself is never listed: its own actions are never recorded.
 */
final class ListAuditedPackagesAction extends Action
{
    /**
     * @param  Authenticatable|null  $viewer  Who asks, as each package's history rule sees them.
     * @return list<Package>
     */
    protected function handle(?Authenticatable $viewer = null): array
    {
        AuditedPackagesListingActionEvent::dispatch();

        $packages = $this->perform($viewer);

        AuditedPackagesListedActionEvent::dispatch(array_map(fn (Package $package): string => $package->key, $packages));

        return $packages;
    }

    /**
     * @return list<Package>
     */
    private function perform(?Authenticatable $viewer): array
    {
        $history = app(History::class);

        $packages = array_values(array_filter(
            app(PackageRegistry::class)->all(),
            fn (Package $package): bool => $package->key !== 'keen'
                && $package->audits()
                && $history->allows($package, $viewer, []),
        ));

        usort($packages, fn (Package $a, Package $b): int => strcmp($a->label, $b->label) ?: strcmp($a->key, $b->key));

        return $packages;
    }
}
