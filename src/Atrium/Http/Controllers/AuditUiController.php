<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Atrium\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use RefactorCircus\Keen\Domains\Audit\Actions\ShowAuditEntryAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * The dashboard's audit log: browse every package's entries, inspect one,
 * and record a note.
 */
final class AuditUiController
{
    public function index(Request $request): View
    {
        $filters = $request->validate(ListAuditEntriesAction::rules());
        $user = $request->user();
        $whole = KeenPlugin::authorizer()->can($user, 'viewAny', AuditEntryModel::class);

        abort_unless($whole || $user !== null, 403);

        // Without the whole log, people read the entries they made or that
        // are about them.
        $entries = app(ListAuditEntriesAction::class)->execute($filters, $whole ? null : $user)->withQueryString();

        /** @var view-string $view */
        $view = 'keen::ui.index';

        return view($view, [
            'entries' => $entries,
            'filters' => $filters,
            'sources' => $this->sources(),
            'mayRecord' => KeenPlugin::authorizer()->can($user, 'create', AuditEntryModel::class),
        ]);
    }

    public function show(Request $request, string $entry): View
    {
        $model = AuditEntryModel::query()->whereKey($entry)->firstOrFail();

        abort_unless(KeenPlugin::authorizer()->can($request->user(), 'view', $model), 403);

        /** @var view-string $view */
        $view = 'keen::ui.show';

        return view($view, [
            'entry' => app(ShowAuditEntryAction::class)->execute($model),
            'personal' => KeenPlugin::authorizer()->can($request->user(), 'viewPersonalData', $model),
            'sources' => $this->sources(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(KeenPlugin::authorizer()->can($request->user(), 'create', AuditEntryModel::class), 403);

        $user = $request->user();

        app(RecordAuditEventAction::class)->execute(
            $request->validate(RecordAuditEventAction::rules()),
            $user instanceof Model ? $user : null,
        );

        return redirect()
            ->route('atrium.keen.entries.index')
            ->with('status', __('keen::keen.recorded'));
    }

    /**
     * Every installed package, by key, plus the application's own entries.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach (app(PackageRegistry::class)->all() as $package) {
            $sources[$package->key] = $package->label;
        }

        asort($sources);

        return $sources + [AuditEntryModel::SOURCE_APP => __('keen::keen.source_app')];
    }
}
