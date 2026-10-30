# History section by capability

## Goal

Keen takes over everything audit-related from the Atrium dashboard: the per-package history page, the `audit-trail`, `audit.entries` and `audit.changes` components, and the History nav items. It also attaches a History section to other packages' screens by wildcard, using only its own config. Hosts declare no audit concept and name no Keen class. Keen still names no host package in code, and Atrium stays a `suggest`.

Builds on Atrium spec `2026-10-29-2037-screen-extensions-registry` (PR #16, which removes `atrium.history.show`, `HistoryController`, `x-atrium::audit-trail`, `x-atrium::audit.*` and `Plugin::historyNavItem()`). This spec merges in the same group as Atrium PR #16 and the host specs (Atrium D-009).

## Acceptance criteria

The gate is `composer test` (static analysis, lint, type coverage, Pest). Keen has no browser suite, so every criterion below is proven by a feature, unit or arch test, or by a file check.

### Atrium only when present

1. **Suggest only.** `composer.json` `require` lists no suite package except `refactor-circus/keystone`, and `refactor-circus/atrium` stays in `suggest` (and `require-dev`). A test reads `composer.json` and asserts both.
2. **Atrium code stays in `src/Atrium`.** An arch test asserts that no class outside `RefactorCircus\Keen\Atrium` uses anything in the `RefactorCircus\Atrium` namespace. `KeenServiceProvider` refers to Atrium only through `RefactorCircus\Keen\Atrium\*` class names, behind the existing `registerAtriumPlugin()` guard (`class_exists` on Atrium plus `keen.ui.enabled === true`). Keen registers its screen extensions (criteria 9 to 12) behind the same guard. A feature test with `keen.ui.enabled` false (set before boot) asserts that `Atrium::screens()` holds no Keen registration and that the `atrium.keen.history.show` route does not exist.

### Components (Atrium D-004, D-014)

3. **Keen components.** Keen ships these anonymous components under `resources/views/components`, ported from Atrium's removed ones with the same props and markup:
   - `x-keen::audit-trail`, with props `source`, `subject`, `scope`, `action`, `limit` (default 20) and `title`
   - `x-keen::audit.entries`, with props `entries` and `withoutSubject`, plus the new props in criterion 8
   - `x-keen::audit.changes`, with prop `changes`

   The ported tests from Atrium's `AuditComponentsTest` cover:
   - the trail renders nothing while the source package doesn't audit
   - it builds the `AuditFilter` from its props (source, subject, scope, action, limit)
   - one record's history leaves out the subject column
   - entries the application recorded get the `App` badge
   - the empty state
   - old and new values in `audit.changes`, and its "No fields changed." text
   - the trail is hidden when `History::allows()` refuses

   They live in `tests/Feature/Ui/AuditComponentsTest.php` and render with `x-keen::`.
4. **Links.** In `x-keen::audit.entries`, each entry's time links to `atrium.keen.entries.show` while that route exists. The trail's "view all" footer (shown when `nextCursor` is set) links to `atrium.keen.history.show` with the package key and the `action`, `subject_type` and `subject_id` filters. A test asserts the "view all" href contains `/atrium/history/shop?subject_type=` and `subject_id=`.
5. **No stylesheet, Keen strings.** The components and every new view use only `x-atrium::*` components and Atrium's safelisted utilities: the existing `tests/Feature/StylesTest.php` passes over the whole `resources/views` tree. All their text comes from `lang/en/keen.php`; a test greps the new views and asserts no `atrium::atrium.` translation key remains. Keen's own `ui/index` and `ui/show` switch from `x-atrium::audit.entries` / `x-atrium::audit.changes` to the `x-keen::` ones.

### History page (Atrium D-014)

6. **Route.** `KeenPlugin::routes()` serves `GET history/{package}` under Atrium's dashboard prefix (so `/atrium/history/{package}` by default), named `atrium.keen.history.show` and handled by `RefactorCircus\Keen\Atrium\Http\Controllers\HistoryUiController::show`. Tests use the route name (testing/test-layers).
7. **Behaviour.** The tests are ported from Atrium's `HistoryPageTest` into `tests/Feature/Ui/HistoryPageTest.php`, against the `shop` fixture package. They assert:
   - **404** for an unknown package, and for a package that doesn't audit (`shop.audit.enabled` false)
   - a validation redirect (302) with session errors for input that fails `History::rules()`
   - **403** when `History::allows($package, $user, $filters)` refuses (`authorizeHistory(fn () => false)`)
   - **200** otherwise:
     - the title is "Shop audit log"
     - entries are filtered by `action`, `subject_type` and `subject_id`, and only the package's own entries show (an `app` entry and another package's entry are absent)
     - the "Older" link carries `cursor=`, and the "Newest" link shows on a cursor page

   The order is: 404 checks, validation, authorization, then the screen resolve (criterion 13).

### Nav items (Atrium D-016)

8. **History nav items.** `KeenPlugin::navigation()` returns Keen's existing "Audit log" item, plus one "History" item (`keen::keen.history`, icon `clipboard-document-list`) for every package in `PackageRegistry::all()` except `keen` itself and the keys in `keen.atrium.history.nav.exclude`. Each item:
   - routes to `atrium.keen.history.show` with that package
   - has sort `keen.atrium.history.nav.sort` (default 90)
   - is grouped under `__(keen.atrium.history.nav.groups.{key})` when that key is set, otherwise under the package's label
   - is authorized by `$package->audits() && History::allows($package, $request->user(), [])`

   With `keen.atrium.history.nav.enabled` false, there are no History items. Feature tests on `/atrium` assert each of these:
   - the `/atrium/history/shop` link shows, labelled "History" and under the group "Shop"
   - it is absent when `shop.audit.enabled` is false
   - it is absent when `History::allows` refuses
   - it is grouped under a configured group
   - it is absent for an excluded key
   - it is absent when nav is disabled
   - there is no item for `keen`

### History section by wildcard (Atrium D-017, D-019)

9. **Registration.** When Atrium is present (criterion 2), Keen registers one section with key `keen.history` and label `keen::keen.history` in slot `keen.atrium.history.slot` (default `after`). It is registered for each target in `keen.atrium.history.screens` (default `['*']`) after normalisation:
   - entries are de-duplicated
   - `*` makes every other entry redundant
   - a `{package}.*` entry makes that package's exact keys redundant

   An invalid target or slot throws `InvalidArgumentException` at boot, through the registry's own validation. Unit tests on `RefactorCircus\Keen\Atrium\HistoryExtensions::register()` against a flushed registry assert:
   - the default registers exactly one `*` section in `after`
   - `['*', 'shop.*', 'shop.product']` registers only `*`
   - `['shop.*', 'shop.product', 'other.a']` registers `shop.*` and `other.a`
   - a custom slot is used
   - `'Bad Target'` throws
10. **Visibility.** The section's `authorize` closure answers false when:
   - the screen key matches any `keen.atrium.history.exclude` pattern (default `['keen.*']`, matched with `Str::is`, read when evaluated)
   - `PackageRegistry::find($screen->package)` is null
   - the package doesn't audit
   - `History::allows($package, $request->user(), $input)` refuses

   `$input` holds `subject_type` (morph class) and `subject_id` (key) of the subject from criterion 11, or is empty when there is no subject. The closure checks in that order and returns at the first refusal. Feature tests, run on fixture host screens, cover each refusal, the passing case, and an excluded key, and show that the resolver never runs for a refused screen (counted by spying on `AuditTrail`).
11. **Subject.** The resolver returns `['source' => $screen->package, 'subject' => $screen->kind === Screen::DETAIL ? $record : null]`. The section view renders `<x-keen::audit-trail :source="$source" :subject="$subject" />`, so a detail screen shows its record's history, and a list screen, or a detail screen without a record, shows the package-wide history. The tests use a fixture route that declares `Screen::standard('shop.product', 'detail')->record($product)`, another that declares `Screen::standard('shop.products', 'list')`, and dispatched `ProductCreatedActionEvent`s. They assert:
    - the detail screen lists only that product's entries, without the subject column
    - the list screen lists every `shop` entry
    - both are wrapped in `data-testid="section-keen.history"` inside the `after` slot
    - nothing renders on `other.screen` from an unregistered package

### Activity tab by config (shape D-003, D-005)

12. **Scoped tab.** For each `keen.atrium.history.tabs` entry `target => ['scope' => bool]` (default `['roster.organization' => ['scope' => true]]`), Keen registers a tab on that target with:
    - key `keen.activity`, label `keen::keen.activity` ("Activity"), icon `clipboard-document-list`
    - view `keen::ui.activity-tab`, which renders `<x-keen::audit-trail :source="$source" :scope="$scope" :subject="$subject" />`
    - a resolver returning `scope` = the record when `scope` is true (otherwise null), and `subject` = the record when `scope` is false (otherwise null)
    - the same `authorize` checks as criterion 10, except `exclude`, with empty `$input` when scoped

   Keen's code names no Roster class or key; only `config/keen.php` does. Feature tests:
   - a fixture screen keyed `roster.organization`, with a `roster` package registered in the test, shows a "Activity" tab whose `?tab=keen.activity` panel lists entries recorded `->in($organization)` and omits entries outside it
   - the tab is hidden, and `?tab=keen.activity` answers 403, when `History::allows` refuses
   - a `['scope' => false]` entry lists the record's own history
   - with no screen keyed `roster.organization`, nothing renders and nothing throws
   - `?tab=activity` opens the default tab

### Keen's own screens (Atrium D-013)

13. **Declared screens.** Each Keen dashboard page declares itself with `Screen::standard()` through `KeenPlugin::screen(string $key, string $kind): Screen`, which also attaches the access closure (criterion 14). The host resolves the screen only after its own authorization:

    | Key | Route | Kind | Record | Row slot rows |
    |---|---|---|---|---|
    | `keen.entries` | `atrium.keen.entries.index` | list | none | each `AuditEntryModel` on the page |
    | `keen.entry` | `atrium.keen.entries.show` | detail | the `AuditEntryModel` | — |
    | `keen.history` | `atrium.keen.history.show` | list | none (a package is not a model) | each `AuditEntryModel` on the page |

    Each screen has only the synthetic `overview` tab. Each view renders, per Atrium criterion 15:
    - `header.actions` inside `page-header`'s actions slot (after `keen.entry`'s existing back button)
    - `x-atrium::screen-tabs`, then either `x-atrium::screen-tab-panel` when a registered tab is open, or `before`, the page content, `after` and, on `keen.entry`, `aside` after the two cards in the grid
    - `row.actions` inside each entry row's last cell, through `x-keen::audit.entries`' new optional props:
      - `screen` (`?ResolvedScreen`)
      - `rows` (`array<int|string, Model>` keyed by entry id)

      When `screen` is null, no row sections render. No column is added, so the markup doesn't change when nothing is registered.

    Feature tests, in `tests/Feature/Ui/KeenScreensTest.php`, run per screen. Each registers a public tab and a public section in every standard slot, and asserts:
    - each section renders, and not in an undeclared slot
    - the tab appears and opens through `?tab=`
    - only `header.actions` and the panel render while the tab is open
    - on the list screens, a row section renders once per row and receives that row's `AuditEntryModel` as `$record`
    - with nothing registered, no `screen-tabs` nav renders, and every existing `SurfacesTest` assertion holds
14. **Access closure.** `KeenPlugin::screen()` attaches `access(fn (string $permission, Request $request, ?Model $record): bool => KeenPlugin::authorizer()->can($request->user(), $permission, $record ?? AuditEntryModel::class))`. This is Keen's existing Keystone `Authorizer` over `keen.policies`. A test with `keen.authorization` on and a `ReadOnlyAuditPolicy`-style policy shows that a `permission('view')` section renders on `keen.entry` for a user the policy lets view, and is hidden for one it doesn't.
15. **History reads Keen's own log.** `HistoryUiController` reads the page with `ListAuditEntriesAction` (the same query `KeenAuditTrail` runs), using `ListAuditEntriesData::from(['source' => $package->key] + $validated)` and no actor restriction (the History check already passed). Its rows are therefore `AuditEntryModel`s for `row.actions`. The view maps them with `toAuditEntry()` for `x-keen::audit.entries`. Pagination uses the cursor paginator's `nextCursor()?->encode()`. The `every action is reachable` parity arch test still passes.

### Tests, docs and wiring

16. **Test reset.** `tests/Pest.php` applies Atrium's `RefactorCircus\Atrium\Testing\ResetsScreenExtensions` to `Feature` and `Modes/RenamedTables`. A test registers a section in one test and asserts it is gone in the next, while Keen's own `keen.history` section is still registered.
17. **Forced test changes only.** Existing tests may change only where this spec's decisions force it:
    - `SurfacesTest` "shows each package's history in its own atrium screens" renders `x-keen::audit-trail` instead of `x-atrium::audit-trail`
    - assertions that read `x-atrium::audit.*` markup through Keen's index and show pages read the same markup from `x-keen::`

    Each changed test is listed in the PR by file and test name.
18. **Docs.** These docs describe the History section, the activity tab, the History nav items, the history page route `atrium.keen.history.show` and the `keen.atrium.history` config, and they replace every `<x-atrium::audit-trail>` example with the fact that History attaches itself (`x-keen::audit-trail` stays documented for apps' own views):
    - the README
    - the Boost skill `resources/boost/skills/keen-development/SKILL.md`
    - `CLAUDE.md`'s "Role in the suite"

    A docs test asserts that neither README nor skill contains `x-atrium::audit` or `atrium.history.show`, and that both contain `keen.atrium.history` and `exclude`. `CHANGELOG.md` gets an entry that names:
    - the new route and components
    - the config keys
    - the replacement for Atrium's removed `atrium.history.show`, `x-atrium::audit-trail`, `x-atrium::audit.*` and `Plugin::historyNavItem()`
19. **Arch.** `tests/ArchTest.php` passes unchanged:
    - the new classes go in `src/Atrium` (`HistoryExtensions`, `Http/Controllers/HistoryUiController`), outside `Domains/`, so the domain Model, Data and Action rules don't apply to them
    - every file declares strict types
    - new classes are `final`

    `composer test` passes.

## Design

**Config** (`config/keen.php`, new "Atrium history" banner under the Atrium section; read with a cast and a hard default each time):

```php
'atrium' => [
    'history' => [
        // Screens the History section attaches to: '*', '{package}.*' or exact keys.
        'screens' => ['*'],
        // The slot it renders in, on every screen that declares it.
        'slot' => 'after',
        // Screen-key patterns it never shows on (Str::is).
        'exclude' => ['keen.*'],
        // Scoped activity tabs: target => ['scope' => true|false].
        'tabs' => ['roster.organization' => ['scope' => true]],
        'nav' => [
            'enabled' => true,
            'sort' => 90,
            'exclude' => [],
            // Package key => nav group (a translation key or a plain label); the package label otherwise.
            'groups' => [
                'showroom' => 'showroom::showroom.catalog',
                'pennantplus' => 'pennantplus::pennantplus.group',
            ],
        ],
    ],
],
```

`keen.ui.enabled` remains the master switch for everything Atrium. The `screens`, `slot` and `tabs` targets are read once at boot, because they are registry targets. `exclude` and `nav` are read each time they are evaluated. Tests that need other boot-time values flush the registry and call `HistoryExtensions::register()` again.

**Classes** (all `final`, strict types, under `src/Atrium`):

- `HistoryExtensions`: `register(): void` reads the config and calls `Atrium::screens()->section()` and `->tab()`. It also holds the shared visibility check `visible(Request, ?Model $subject, Screen, bool $checkExclude): bool` and the target normalisation. `KeenServiceProvider::boot()` calls it next to `registerAtriumPlugin()`, behind the same guard.
- `Http/Controllers/HistoryUiController::show(Request, string $package)`: ported from Atrium's `HistoryController`, using `History::rules()` and `History::allows()`, the read from criterion 15, and the view `keen::ui.history`.
- `KeenPlugin`:
  - `navigation()` gains the History items
  - `routes()` gains `Route::get('history/{package}', …)->name('history.show')` inside its `keen.` name group, giving the full name `atrium.keen.history.show` and the path `atrium/history/{package}`
  - a new `public static function screen(string $key, string $kind): Screen`
- `AuditUiController::index` and `show`: build and resolve their screens and pass `resolved` and `rows` to the views.

**Views:**

- `components/audit-trail`, `components/audit/entries` and `components/audit/changes`: ported
- `ui/history`: the history page
- `ui/history-section`: the section body
- `ui/activity-tab`: the tab panel
- `ui/index` and `ui/show`: updated for the screen layout

Translations move into `lang/en/keen.php`: `history` ("History"), `activity` ("Activity"), `history_for` (":package audit log"), `empty`, `when`, `app_badge` ("App"), `view_all`, `newest`, `older`, `no_changes`, `field`, `before`, `after`. The existing `action`, `subject`, `actor`, `surface`, `none`, `system`, `filter`, `clear`, `action_hint`, `subject_type` and `subject_id` are reused.

**Route name decision.** The path stays `history/{package}` under Atrium's prefix, so URLs that users bookmarked keep working. The name becomes `atrium.keen.history.show`, not `atrium.history.show`, because:

- every Keen dashboard route already sits under Keen's `keen.` name group (`atrium.keen.entries.*`)
- a plugin defining names in Atrium's own `atrium.*` namespace would make Keen claim a name Atrium may reuse
- nothing is on Packagist, and the merge is grouped (Atrium D-009), so no caller outside the group breaks

Hosts never linked to the route by name except through `historyNavItem()`, which Atrium removes.

**Standards.**

- architecture/dependencies: Atrium is referenced only from `src/Atrium`, behind `class_exists`.
- architecture/configuration: one config file, documented banner, casts and defaults on read.
- http/authorization: the host checks first, then `History::allows()`, which applies the package's own rule. For Roster that rule is `roster.audit.view`, so Keen needs no Roster permission.
- testing/test-layers: wiring is tested at the surface, with fixture hosts in Keen's suite.
- The atrium component rule (no CSS) is enforced by the existing `StylesTest`. It is not in Keen's standards index.

## Out of scope

- Removing the inline `<x-atrium::audit-trail>` and `historyNavItem()` calls in Roster, Showroom, Polycart, PennantPlus, Cortex and Impex. Each host spec does that.
- Roster's own `roster.organization` screen declaration and its `?tab=activity` fallback (Roster spec, D-005).
- A scope filter on the history page. The scoped tab's "view all" link, like today's, carries only action and subject filters.
- Per-package nav sort overrides, per-screen slot overrides, and translated labels for registered tabs.
- Changing `AuditTrail`, `History` or `Package::audits()` in Keystone.

## Risks and open questions

- **Q1: Should `exclude` default to more than `keen.*`?** Atrium registers as package `atrium` with `audit.enabled` true, so with the default `['*']` the History section appears in the `after` slot of the dashboard (`atrium.dashboard`, `atrium.named-dashboard`), widget, settings and settings-panel screens, showing the dashboard's or Atrium's history. Options:
  - (a) keep `['keen.*']` and let apps exclude more
  - (b) default to `['keen.*', 'atrium.*']`
  - (c) exclude only the dashboard keys

  **Recommendation: (b).** The dashboard and settings pages are workspace chrome, not records, and a History card under every dashboard is noise. It is one editable config line.
- **Q2: How should History nav items be grouped?** Hosts used to place their own "Audit log" item in their own group, and two groups differ from the package label: Showroom's is `Catalog` and PennantPlus's is `System`. Options:
  - (a) group under the package label only
  - (b) package label plus the config `groups` map with defaults for those two (as designed)
  - (c) one "History" parent item under Keen with a child per package

  **Recommendation: (b).** It keeps each History item where users found it, and only config names the other packages (D-019). PennantPlus's old sort (910) is not kept; every item uses `nav.sort`.
- **Q3: Should the nav label be "History" or "Audit log"?** The removed `historyNavItem()` said "Audit log", which is also Keen's own top item. **Recommendation: "History"**, as the decisions name it, so it is not confused with Keen's whole-log item. Hosts' browser tests that asserted "Audit log" in their groups change in the host specs.
- **Q4: Should the route name be `atrium.keen.history.show` or keep `atrium.history.show`?** Settled above as `atrium.keen.history.show`. It is raised here only so spec review can confirm, since every host spec's grep for `atrium.history` depends on it.
- **Risk: double checks.** The section's `authorize` and then the trail component both run `History::allows()`, which loads the subject again through `find()`. That is two small queries per audited detail screen. It is accepted so that `x-keen::audit-trail` stays safe when apps place it themselves. `PerformanceTest` must still pass.
- **Risk: safelist.** Atrium PR #16 drops the `@source` lines for the audit components, so utilities used only by them (`underline-offset-2`, `hover:underline`, `break-all`, `opacity-60`) may leave Atrium's compiled safelist. `StylesTest` catches this, and the builder then switches to safelisted utilities. Keen ships no CSS.
- **Risk: open access.** With `keen.authorization` off, `KeenPlugin::authorizer()->can()` grants everything, so any `permission()` extension on Keen's screens shows to every viewer. This matches how Keen's own pages behave when authorization is off. Registrants that need their own check use `authorize()`.
- **Risk: wildcard cost.** The section's closure runs on every declared screen. It returns early on `exclude` and on unknown or non-auditing packages before `History::allows()`.
- **Merge order.** This lands with Atrium PR #16 and the host specs. Until a host removes its inline trail, a screen could show history twice (inline plus the section). Before Atrium PR #16 merges, Keen's `atrium/history/{package}` path would collide with Atrium's. Both are resolved by the grouped merge.
