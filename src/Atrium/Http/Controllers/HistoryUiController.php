<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Services\ScreenExtensions;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * One package's audit log, the `keen.history` screen: its entries, newest
 * first, filtered by action or record, a page at a time. Answers 404 for an
 * unknown package or one that does not audit, and 403 to those the package's
 * history endpoint would refuse.
 */
final class HistoryUiController
{
    public function show(Request $request, string $package): View
    {
        $found = app(PackageRegistry::class)->find($package);

        // Only while the package audits: its `audit.enabled` on.
        abort_if($found === null || ! $found->audits(), 404);

        $validated = $request->validate(History::rules());

        abort_unless(app(History::class)->allows($found, $request->user(), $validated), 403);

        // The route's package is the source, whatever the input says; the
        // History check above already decided who may read it.
        $entries = app(ListAuditEntriesAction::class)->execute(ListAuditEntriesData::from(['source' => $found->key] + $validated));

        $resolved = app(ScreenExtensions::class)->resolve(KeenPlugin::screen('keen.history', Screen::LIST), $request);

        /** @var view-string $view */
        $view = 'keen::ui.history';

        return view($view, [
            'resolved' => $resolved,
            'package' => $found,
            'filters' => $validated,
            'entries' => $entries,
            'nextCursor' => $entries->nextCursor()?->encode(),
            'rows' => $this->rows($entries->items()),
        ]);
    }

    /**
     * Each entry on the page by id, as the model its `row.actions` sections
     * receive.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<int, AuditEntryModel>
     */
    private function rows(array $items): array
    {
        $rows = [];

        foreach ($items as $entry) {
            if ($entry instanceof AuditEntryModel) {
                $rows[(int) $entry->getKey()] = $entry;
            }
        }

        return $rows;
    }
}
