<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Atrium;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Atrium\Domains\Plugins\Support\Plugin;
use RefactorCircus\Atrium\Support\Icons;
use RefactorCircus\Foundation\Auth\Authorizer;
use RefactorCircus\Foundation\Packages\PackageRegistry;
use RefactorCircus\Keen\Atrium\Http\Controllers\AuditUiController;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * The audit log's own section in the Atrium dashboard: every package's
 * entries in one place. Each package's screens show their own history with
 * `<x-atrium::audit-trail>`.
 */
class KeenPlugin extends Plugin
{
    public function key(): string
    {
        return 'keen';
    }

    public function label(): string
    {
        return __('keen::keen.label');
    }

    public function navigation(): array
    {
        return [
            NavItem::make(__('keen::keen.audit_log'))
                ->icon(Icons::svg('clipboard-document-list'))
                ->route('atrium.keen.entries.index')
                ->sort(90)
                ->authorize(fn (Request $request): bool => self::authorizer()->can($request->user(), 'viewAny', AuditEntryModel::class)),
        ];
    }

    public function routes(): void
    {
        Route::name('keen.')->group(function (): void {
            Route::get('keen/entries', [AuditUiController::class, 'index'])->name('entries.index');
            Route::post('keen/entries', [AuditUiController::class, 'store'])->name('entries.store');
            Route::get('keen/entries/{entry}', [AuditUiController::class, 'show'])->whereNumber('entry')->name('entries.show');
        });
    }

    public static function authorizer(): Authorizer
    {
        return Authorizer::for(app(PackageRegistry::class)->get('keen'));
    }
}
