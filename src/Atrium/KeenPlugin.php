<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Atrium;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Atrium\Domains\Plugins\Support\Plugin;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Support\Icons;
use RefactorCircus\Keen\Atrium\Http\Controllers\AuditUiController;
use RefactorCircus\Keen\Atrium\Http\Controllers\HistoryUiController;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Auth\Authorizer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * The audit log's own section in the Atrium dashboard: every package's
 * entries in one place, each package's own history page, and a "History"
 * nav item for every package that audits.
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
            ...$this->historyItems(),
        ];
    }

    public function routes(): void
    {
        Route::name('keen.')->group(function (): void {
            Route::get('keen/entries', [AuditUiController::class, 'index'])->name('entries.index');
            Route::post('keen/entries', [AuditUiController::class, 'store'])->name('entries.store');
            Route::get('keen/entries/{entry}', [AuditUiController::class, 'show'])->whereNumber('entry')->name('entries.show');
            Route::get('history/{package}', [HistoryUiController::class, 'show'])->name('history.show');
        });
    }

    public static function authorizer(): Authorizer
    {
        return Authorizer::for(app(PackageRegistry::class)->get('keen'));
    }

    /**
     * One of Keen's dashboard pages as a screen with the standard slots,
     * whose extensions' `permission()`s Keen's own policies decide: on the
     * screen's record or a row's entry, otherwise on AuditEntryModel.
     */
    public static function screen(string $key, string $kind): Screen
    {
        return Screen::standard($key, $kind)->access(
            fn (string $permission, Request $request, ?Model $record): bool => self::authorizer()->can($request->user(), $permission, $record ?? AuditEntryModel::class),
        );
    }

    /**
     * A "History" item for every package but Keen and those the config
     * excludes, linking to its history page. Who sees it is decided only when
     * the item renders: while the package audits and its history rule allows.
     *
     * @return list<NavItem>
     */
    private function historyItems(): array
    {
        if (! (bool) config('keen.atrium.history.nav.enabled', true)) {
            return [];
        }

        $sort = config('keen.atrium.history.nav.sort', 90);
        $exclude = self::strings(config('keen.atrium.history.nav.exclude', []));
        $groups = config('keen.atrium.history.nav.groups', []);
        $groups = is_array($groups) ? $groups : [];
        $items = [];

        foreach (app(PackageRegistry::class)->all() as $package) {
            if ($package->key === $this->key() || in_array($package->key, $exclude, true)) {
                continue;
            }

            $items[] = NavItem::make(__('keen::keen.history'))
                ->icon(Icons::svg('clipboard-document-list'))
                ->route('atrium.keen.history.show', ['package' => $package->key])
                ->group(self::group($package, $groups[$package->key] ?? null))
                ->sort(is_numeric($sort) ? (int) $sort : 90)
                ->authorize(fn (Request $request): bool => $package->audits() && app(History::class)->allows($package, $request->user(), []));
        }

        return $items;
    }

    /**
     * The configured group (a translation key or a plain label), or the
     * package's label.
     */
    private static function group(Package $package, mixed $configured): string
    {
        if (! is_string($configured) || $configured === '') {
            return $package->label;
        }

        $label = __($configured);

        return is_string($label) ? $label : $configured;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        $strings = [];

        foreach (is_array($value) ? $value : [$value] as $entry) {
            if (is_string($entry)) {
                $strings[] = $entry;
            }
        }

        return $strings;
    }
}
