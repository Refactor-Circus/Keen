# History section by capability

## Goal

Keen takes over everything audit-related from the Atrium dashboard: the per-package history page, the `audit-trail`, `audit.entries` and `audit.changes` components, and the History nav items. It also attaches a History section to other packages' screens by wildcard, using only its own config. Hosts declare no audit concept and name no Keen class. Keen still names no host package in code, and Atrium stays a `suggest`, now with a composer `conflict` on Atrium releases that predate the screen registry (D-029). Keen proves it runs without Atrium, both in-process inside `composer test` and in a CI lane with Atrium uninstalled (D-020).

Builds on Atrium spec `2026-10-29-2037-screen-extensions-registry` (PR #16, which removes `atrium.history.show`, `HistoryController`, `x-atrium::audit-trail`, `x-atrium::audit.*` and `Plugin::historyNavItem()`). This spec merges in the same group as Atrium PR #16 and the host specs (Atrium D-009).

## Acceptance criteria

The gate is `composer test` (static analysis, lint, type coverage, Pest, and the new `test:without-atrium` suite from criterion 20). Keen has no browser suite, so every criterion below is proven by a feature, unit or arch test, or by a file check, except the CI lane in criterion 20, which is proven by the workflow run.

### Atrium only when present

1. **Suggest only, and no pre-registry Atrium.** `composer.json`:
   - `require` lists no suite package except `refactor-circus/keystone`
   - `refactor-circus/atrium` stays in `suggest` and `require-dev` (`dev-main`)
   - a new `conflict` block holds `"refactor-circus/atrium": "<0.2.0"` (D-029). Atrium has no tagged release yet (its CHANGELOG names only the `v0.1.0` placeholder), so the first tagged release can contain the registry only from `0.2.0` on. `dev-main` installs, which is what the suite uses today, are not matched by the constraint.

   `composer.local.json` is regenerated with `bin/composer-local.php` so it carries the same `conflict`. A test reads `composer.json` and asserts all three points, including the exact `conflict` value.
2. **Atrium code stays in `src/Atrium`.** `tests/ArchTest.php` gains one rule: `arch('only the Atrium layer uses Atrium')->expect('RefactorCircus\Keen')->not->toUse('RefactorCircus\Atrium')->ignoring('RefactorCircus\Keen\Atrium')`. `KeenServiceProvider` refers to Atrium only through `RefactorCircus\Keen\Atrium\*` class names, behind the existing `registerAtriumPlugin()` guard (`class_exists('RefactorCircus\Atrium\Atrium')` plus `keen.ui.enabled === true`). Keen registers its screen extensions (criteria 9 to 12) behind the same guard, through `HistoryExtensions::registerIfSupported(string $atrium = Atrium::class): void`. It returns without registering anything unless `method_exists($atrium, 'screens')`, so an Atrium release from before the registry boots cleanly and gets no screen extensions (the `conflict` in criterion 1 is the first line of defence; this guard is the second). Feature tests assert:
   - with `keen.ui.enabled` false (set before boot), `Atrium::screens()` holds no Keen registration and the `atrium.keen.history.show` route does not exist
   - `HistoryExtensions::registerIfSupported(PreRegistryAtrium::class)`, with a test fixture class that has no `screens()` method, returns without throwing and registers nothing (the registry snapshot is unchanged), and a request to `/atrium/keen/entries` still answers 200

### Components (Atrium D-004, D-014)

3. **Keen components.** Keen ships these anonymous components under `resources/views/components`, ported from Atrium's removed ones with the same props and markup:
   - `x-keen::audit-trail`, with props `source`, `subject`, `scope`, `action`, `limit` (default 20) and `title`
   - `x-keen::audit.entries`, with props `entries` and `withoutSubject`, plus the new props in criterion 13
   - `x-keen::audit.changes`, with prop `changes`

   The ported tests from Atrium's `AuditComponentsTest` live in `tests/Feature/Ui/AuditComponentsTest.php`, render with `x-keen::`, and cover:
   - the trail renders nothing while the source package doesn't audit
   - it builds the `AuditFilter` from its props (source, subject, scope, action, limit)
   - one record's history leaves out the subject column
   - entries the application recorded get the `App` badge
   - the empty state
   - old and new values in `audit.changes`, and its "No fields changed." text
   - the trail is hidden when `History::allows()` refuses
4. **Links.** In `x-keen::audit.entries`, each entry's time links to `atrium.keen.entries.show` while that route exists. The trail's "view all" footer (shown when `nextCursor` is set) links to `atrium.keen.history.show` with the package key and the `action`, `subject_type` and `subject_id` filters. Tests assert:
   - the "view all" href contains `/atrium/history/shop?subject_type=` and `subject_id=`
   - with the route registered (the Keen dashboard on), each entry's time href is the `atrium.keen.entries.show` URL for that entry
   - with the route absent (the component rendered standalone with `keen.ui.enabled` false), the time renders as plain text with no `<a>` around it
5. **No stylesheet, Keen strings.** The components and every new view use only `x-atrium::*` components and Atrium's safelisted utilities: the existing `tests/Feature/StylesTest.php` passes over the whole `resources/views` tree. All their text comes from `lang/en/keen.php`. Two tests cover this:
   - one greps the new views and asserts no `atrium::atrium.` translation key remains
   - one collects every `__('keen::keen.*')` key used in the new views and asserts each exists in `lang/en/keen.php` (a missing key would render as the key string), including `history`, `activity`, `history_for`, `empty`, `when`, `app_badge`, `view_all`, `newest`, `older`, `no_changes`, `field`, `before` and `after`

   Keen's own `ui/index` and `ui/show` switch from `x-atrium::audit.entries` / `x-atrium::audit.changes` to the `x-keen::` ones. The ported markup needs no Atrium change: every utility it uses is in Atrium's `@source inline(...)` safelist (see Risks, "Safelist").

### History page (Atrium D-014)

6. **Route.** `KeenPlugin::routes()` serves `GET history/{package}` under Atrium's dashboard prefix (so `/atrium/history/{package}` by default), named `atrium.keen.history.show` (D-027) and handled by `RefactorCircus\Keen\Atrium\Http\Controllers\HistoryUiController::show`. Tests use the route name (testing/test-layers), and one asserts that a request to `/atrium/history/shop` is dispatched to `HistoryUiController` (not shadowed by another route).
7. **Behaviour.** The tests are ported from Atrium's `HistoryPageTest` into `tests/Feature/Ui/HistoryPageTest.php`, against the `shop` fixture package. They assert:
   - **404** for an unknown package, and for a package that doesn't audit (`shop.audit.enabled` false)
   - a validation redirect (302) with session errors for input that fails `History::rules()`: the test uses `subject_id` without `subject_type` where the rules require them together, and a `limit` one above the maximum the rules allow (the test reads both from `History::rules()`)
   - the source can't be overridden: a request with `?source=other` still lists only the route package's entries (criterion 15)
   - **403** when `History::allows($package, $user, $filters)` refuses (`authorizeHistory(fn () => false)`)
   - **200** otherwise:
     - the title is "Shop audit log"
     - entries are filtered by `action`, `subject_type` and `subject_id`, and only the package's own entries show (an `app` entry and another package's entry are absent)
     - the "Older" link carries `cursor=`, and the "Newest" link shows on a cursor page (the fixture records 5 `shop` entries and the request passes `limit=2`, so a second and third page exist)

   The order is: 404 checks, validation, authorization, then the screen resolve (criterion 13).

### Nav items (Atrium D-016)

8. **History nav items.** `KeenPlugin::navigation()` returns Keen's existing "Audit log" item, plus one item labelled "History" (`keen::keen.history`, D-024; icon `clipboard-document-list`) for every package in `PackageRegistry::all()` except `keen` itself and the keys in `keen.atrium.history.nav.exclude`. Each item:
   - routes to `atrium.keen.history.show` with that package
   - has sort `keen.atrium.history.nav.sort` (default 90)
   - is grouped under `__(keen.atrium.history.nav.groups.{key})` when that key is set, otherwise under the package's label (D-023). The shipped `groups` defaults are `showroom => showroom::showroom.catalog` and `pennantplus => pennantplus::pennantplus.group`, so those two keep the groups their old items sat in. Only config names those packages.
   - is authorized by `$package->audits() && History::allows($package, $request->user(), [])`, resolved lazily through the nav API's authorization callback so nothing is checked until an item is rendered for a viewer

   With `keen.atrium.history.nav.enabled` false, there are no History items. Feature tests on `/atrium` assert each of these:
   - the `/atrium/history/shop` link shows, labelled "History" and under the group "Shop"
   - it is absent when `shop.audit.enabled` is false
   - it is absent when `History::allows` refuses
   - it is grouped under a configured group (a test sets `groups.shop` and asserts the group label)
   - the shipped config holds exactly the `showroom` and `pennantplus` `groups` defaults above
   - it is absent for an excluded key
   - it is absent when nav is disabled
   - there is no item for `keen`
   - a query-count test on `/atrium` with several audited packages registered holds the audit queries at a fixed upper bound that does not grow with the package count

### History section by wildcard (Atrium D-017, D-019)

9. **Registration and bad config.** When Atrium is present (criterion 2), Keen registers one section with key `keen.history` and label `keen::keen.history` in slot `keen.atrium.history.slot` (default `after`). It is registered for each target in `keen.atrium.history.screens` (default `['*']`) after normalisation:
   - entries are de-duplicated
   - `*` makes every other entry redundant
   - a `{package}.*` entry makes that package's exact keys redundant

   Section keys and screen keys are separate namespaces: the section `keen.history` is an extension, and `keen.history` is also the key of Keen's own history screen (criterion 13). With the default `exclude` (criterion 10), the section never attaches to that screen.

   Values are read with casts and hard defaults, so malformed shapes are tolerated: `screens` and `exclude` are wrapped into arrays with non-string entries dropped, a `tabs` entry whose value isn't an array is treated as `[]`, and `scope` is cast to bool.

   An invalid target string (in `screens` or as a `tabs` key) or an invalid `slot` follows the suite rule (D-021, which also settles D-028). `HistoryExtensions` validates each entry before it calls the registry, with the same patterns Atrium enforces: the target is `*`, `{package}.*` (`/^[a-z0-9-]+\.\*$/`) or matches `Screen::KEY_PATTERN`, and the slot matches `Screen::NAME_PATTERN`. Then:
   - in the `local` and `testing` environments, it throws `InvalidArgumentException` naming the config key and the bad value
   - in every other environment, it calls `report()` once for that entry (an `InvalidArgumentException`, which the default log handler writes as an error), skips the entry, registers the valid ones, and boot continues. An invalid `slot` skips the section entirely, because every section target shares it; the tabs still register.

   Tests in `tests/Feature/Ui/HistoryExtensionsTest.php` call `RefactorCircus\Keen\Atrium\HistoryExtensions::register()` against a flushed registry and assert:
   - the default registers exactly one `*` section in `after`
   - `['*', 'shop.*', 'shop.product']` registers only `*`
   - `['shop.*', 'shop.product', 'other.a']` registers `shop.*` and `other.a`
   - a custom slot is used
   - in `testing`, `'Bad Target'` in `screens`, a bad `tabs` key and an invalid slot each throw `InvalidArgumentException`
   - in `production` (the test sets `app.env` and fakes the exception handler), `['Bad Target', 'shop.*']` reports exactly once, registers `shop.*` and does not throw; an invalid slot reports once, registers no section and still registers the default tab
   - malformed shapes (`screens` as a string, `tabs` with a non-array entry, `scope` as `'1'`) neither throw a TypeError nor register anything unexpected

   A boot-time feature test with `keen.atrium.history.screens` set to `['Bad Target']` before boot asserts the `InvalidArgumentException` surfaces from the provider in `testing`.
10. **Visibility.** The section's `authorize` closure answers false when:
    - the screen key matches any `keen.atrium.history.exclude` pattern (default `['keen.*', 'atrium.*']`, D-022/D-026; matched with `Str::is`, read when evaluated)
    - `PackageRegistry::find($screen->package)` is null
    - the package doesn't audit
    - `History::allows($package, $request->user(), $input)` refuses
    - the screen is a detail screen and its record isn't an Eloquent model

    `$input` holds `subject_type` (morph class) and `subject_id` (key) of the subject from criterion 11, or is empty when there is no subject. The closure checks in that order and returns at the first refusal. Feature tests, run on fixture host screens, cover each refusal, the passing case, and an excluded key. They assert:
    - with the shipped default, the section does not show on an `atrium.dashboard` fixture screen or on the `keen.history` screen, and does show on a `shop.product` fixture screen
    - with `exclude` set to `['keen.*']`, it shows on the `atrium.dashboard` fixture screen, which proves the default is what hides it
    - a test reads the shipped `config/keen.php` and asserts `exclude` is exactly `['keen.*', 'atrium.*']`

    The resolver never runs for a refused screen: the tests bind a counting fake of Keystone's `AuditTrail` in the container and assert zero `list` calls and no `section-keen.history` testid on each refused screen (with `History::allows` stubbed through `authorizeHistory`). Query-count tests assert that an audited detail screen runs at most a fixed number of queries against the audit table (stated in the test), and that an excluded or non-auditing screen runs none.
11. **Subject.** The resolver returns `['source' => $screen->package, 'subject' => $screen->kind === Screen::DETAIL ? $record : null]`. The section view `keen::ui.history-section` renders `<x-keen::audit-trail :source="$source" :subject="$subject" />`, so a detail screen shows its record's history, and a list screen, or a detail screen without a record, shows the package-wide history. The tests use a fixture route that declares `Screen::standard('shop.product', 'detail')->record($product)`, another that declares `Screen::standard('shop.products', 'list')`, and dispatched `ProductCreatedActionEvent`s. They assert:
    - the detail screen lists only that product's entries, without the subject column
    - the list screen lists every `shop` entry
    - both are wrapped in `data-testid="section-keen.history"` inside the `after` slot
    - nothing renders on `other.screen` from an unregistered package
    - a detail screen whose record isn't an Eloquent model shows no section

    The subject's `subject_type` is read as the morph class, so an unmapped model puts its FQCN into the "view all" URL, and a record that isn't an audited subject of that package shows an empty history. The README and skill say so.

### Activity tab by config (shape D-003, D-005)

12. **Scoped tab.** For each `keen.atrium.history.tabs` entry `target => ['scope' => bool]` (default `['roster.organization' => ['scope' => true]]`), Keen registers a tab on that target with:
    - key `keen.activity`, label `keen::keen.activity` ("Activity"), icon `clipboard-document-list`
    - view `keen::ui.activity-tab`, which renders `<x-keen::audit-trail :source="$source" :scope="$scope" :subject="$subject" />`
    - a resolver returning `scope` = the record when `scope` is true (otherwise null), and `subject` = the record when `scope` is false (otherwise null)
    - the same `authorize` checks as criterion 10, except `exclude`, with empty `$input` when scoped

    Keen's code names no Roster class or key; only `config/keen.php` does. Feature tests:
    - a fixture screen keyed `roster.organization`, with a `roster` package registered in the test, shows an "Activity" tab whose `?tab=keen.activity` panel lists entries recorded `->in($organization)` and omits entries outside it
    - when `History::allows` refuses, the tab is hidden: the page has no "Activity" text and no `section-keen.history` testid, and a request with `?tab=keen.activity` answers **403**. This is Atrium's behaviour for a registered tab the viewer may not see (`ScreenExtensions::resolve()` throws `AccessDeniedHttpException`), which D-030 adopts as is.
    - scope isolation rests on the host's screen authorization, because `History::allows` takes only package-wide input here. A test with a user who may view the package but not a second organization's record asserts that the second organization's screen is refused by the host and shows none of its entries, and that the first organization's tab lists none of the second's entries
    - a `['scope' => false]` entry lists the record's own history
    - with no screen keyed `roster.organization`, a request to another screen answers 200 with no exception, no "Activity" text and no `section-keen.history` testid
    - Keen registers no tab keyed `activity`; the `?tab=activity` fallback is tested in the Roster spec, not here

### Keen's own screens (Atrium D-013)

13. **Declared screens.** Each Keen dashboard page declares itself with `Screen::standard()` through `KeenPlugin::screen(string $key, string $kind): Screen`, which also attaches the access closure (criterion 14). The host resolves the screen only after its own authorization:

    | Key | Route | Kind | Record | Row slot rows |
    |---|---|---|---|---|
    | `keen.entries` | `atrium.keen.entries.index` | list | none | each `AuditEntryModel` on the page |
    | `keen.entry` | `atrium.keen.entries.show` | detail | the `AuditEntryModel` | — |
    | `keen.history` | `atrium.keen.history.show` | list | none (a package is not a model) | each `AuditEntryModel` on the page |

    Each screen has only the synthetic `overview` tab, which is the default tab. Each screen declares the standard slots only (D-025); no screen declares a `history` slot:

    | Screen | Slots |
    |---|---|
    | `keen.entries` | `header.actions`, `before`, `after`, `row.actions` |
    | `keen.entry` | `header.actions`, `before`, `after`, `aside` |
    | `keen.history` | `header.actions`, `before`, `after`, `row.actions` |

    Each view renders, per Atrium criterion 15:
    - `header.actions` inside `page-header`'s actions slot (after `keen.entry`'s existing back button)
    - `x-atrium::screen-tabs`, then either `x-atrium::screen-tab-panel` when a registered tab is open, or `before`, the page content, `after` and, on `keen.entry`, `aside` after the two cards in the grid
    - `row.actions` inside each entry row's last cell, through `x-keen::audit.entries`' new optional props:
      - `screen` (`?ResolvedScreen`, default null)
      - `rows` (`array<int|string, Model>` keyed by entry id, default `[]`)

      When `screen` is null, no row sections render. No column is added, so the markup doesn't change when nothing is registered.

    Feature tests, in `tests/Feature/Ui/KeenScreensTest.php`, run per screen. Each registers a public tab and, in every slot in the table, a public section whose stub view emits `data-testid="probe-{slot}"`, and asserts on those probes:
    - each probe renders once, and a section registered in a slot the screen doesn't declare (`aside` on the two list screens, `row.actions` on `keen.entry`, and `history` on all three) renders no probe
    - the tab appears and opens through `?tab=`
    - only the `probe-header.actions` and the tab's own panel render while the tab is open
    - on the list screens, `probe-row.actions` occurs as many times as the page has rows, and each receives that row's `AuditEntryModel` as `$record`

    A separate structural test (D-032), in `tests/Feature/Ui/NothingRegisteredTest.php`, is the guarantee that markup is unchanged when nothing is registered, independent of criterion 17's allowance. With the registry flushed and Keen's own section and tab not registered, it asserts on the entries index, the entry show and the history page:
    - no `screen-tabs` nav element
    - no element whose `data-testid` starts with `section-`
    - the same number of table header cells as before this change (the test states each number: it is the count the current `ui/index` renders, and the count the ported `history/show` rendered in Atrium)

    It uses no snapshot. Every existing `SurfacesTest` assertion still holds, apart from the forced changes in criterion 17.
14. **Access closure.** `KeenPlugin::screen()` attaches `access(fn (string $permission, Request $request, ?Model $record): bool => KeenPlugin::authorizer()->can($request->user(), $permission, $record ?? AuditEntryModel::class))`. This is Keen's existing Keystone `Authorizer` over `keen.policies`. A test with `keen.authorization` on and a `ReadOnlyAuditPolicy`-style policy shows that a `permission('view')` section renders on `keen.entry` for a user the policy lets view, and is hidden for one it doesn't. Further cases cover a list screen (`$record` null, so the check runs against `AuditEntryModel::class`) and an unauthenticated request (`$request->user()` null).
15. **History reads Keen's own log.** `HistoryUiController` reads the page with `ListAuditEntriesAction` (the same query `KeenAuditTrail` runs), using `ListAuditEntriesData::from(['source' => $package->key] + $validated)` and no actor restriction (the History check already passed). Its rows are therefore `AuditEntryModel`s for `row.actions`. The view maps them with `toAuditEntry()` for `x-keen::audit.entries`. Pagination uses the cursor paginator's `nextCursor()?->encode()`. The `every action is reachable` parity arch test still passes. A test with `?source=other` in the query string asserts that only the route package's entries show, since `['source' => $package->key] + $validated` keeps the route's value.

### Tests, docs and wiring

16. **Test reset.** `tests/Pest.php` applies Atrium's `RefactorCircus\Atrium\Testing\ResetsScreenExtensions` to `Feature` and `Modes/RenamedTables`. One test proves it by calling the hooks directly (D-031): it calls `$this->setUpResetsScreenExtensions()`, registers a custom section `test.probe` on `*`, calls `$this->tearDownResetsScreenExtensions()`, and then asserts that `test.probe` is no longer in `Atrium::screens()->sections('*', 'after')` while `keen.history` still is.
17. **Forced test changes only.** Existing tests may change only where this spec's decisions force it:
    - `tests/Feature/SurfacesTest.php`, "shows each package's history in its own atrium screens": renders `x-keen::audit-trail` instead of `x-atrium::audit-trail`
    - assertions that read `x-atrium::audit.*` markup through Keen's index and show pages read the same markup from `x-keen::`
    - `tests/Pest.php`: gains `ResetsScreenExtensions` (criterion 16)
    - `tests/ArchTest.php`: gains only the Atrium-boundary rule (criterion 2)

    Each changed test is listed in the PR by file and test name. Criterion 13's structural test guards the markup independently of this list.
18. **Docs.** These docs describe the History section, the activity tab, the History nav items, the history page route `atrium.keen.history.show`, the `keen.atrium.history` config (including what happens to a bad entry in local/testing and in production), the Atrium `conflict`, and that the CI lane is the authoritative no-Atrium proof. They replace every `<x-atrium::audit-trail>` example with the fact that History attaches itself (`x-keen::audit-trail` stays documented for apps' own views):
    - the README
    - the Boost skill `resources/boost/skills/keen-development/SKILL.md`
    - `CLAUDE.md`'s "Role in the suite"

    A file check asserts that neither README nor the skill contains the removed names `x-atrium::audit` or `atrium.history.show`. It checks nothing else about their prose; that the new names are documented is a review item. `CHANGELOG.md` gets an entry that names:
    - the new route and components
    - the config keys
    - the replacement for Atrium's removed `atrium.history.show`, `x-atrium::audit-trail`, `x-atrium::audit.*` and `Plugin::historyNavItem()`
    - the `conflict` on `refactor-circus/atrium <0.2.0`
19. **Arch.** `tests/ArchTest.php` passes, with its existing rules unchanged and only the rule from criterion 2 added:
    - the new classes go in `src/Atrium` (`HistoryExtensions`, `Http/Controllers/HistoryUiController`), outside `Domains/`, so the domain Model, Data and Action rules don't apply to them
    - every file declares strict types
    - new classes are `final`

### Without Atrium (D-020)

20. **No-Atrium proof, both ways.**
    - **Files:** `phpunit.without-atrium.xml`, bootstrap `tests/WithoutAtrium/bootstrap.php`, tests in `tests/WithoutAtrium`, and a `tests/WithoutAtrium/WithoutAtriumTestCase.php` that registers `McpServiceProvider`, `KeenServiceProvider` and `ShopServiceProvider` and no Atrium provider. The directory is outside `phpunit.xml.dist`'s suites. The bootstrap loads Composer's autoloader and removes the `RefactorCircus\Atrium\` PSR-4 prefix, so no Atrium class can load. It throws if `ClassLoader::findFile('RefactorCircus\Atrium\Atrium')` still resolves, and the suite must not run with an optimized or authoritative classmap. This in-process run cannot see classmap or Composer `files` leaks, so the CI lane is the authoritative proof.
    - **Assertions:**
      - the PSR-4 prefixes of Composer's `ClassLoader` no longer contain `RefactorCircus\Atrium\` (first test, so the removal is proven before the rest)
      - `class_exists('RefactorCircus\Atrium\Atrium')` is false
      - the app boots with `KeenServiceProvider`, and `class_exists(HistoryExtensions::class, false)` and `class_exists(KeenPlugin::class, false)` are both false after boot (neither was loaded)
      - no route name starts with `atrium.`
      - dispatching a fixture `ProductCreatedActionEvent` records an entry, and `GET /api/keen/entries` answers 200 and lists it
      - `mcpTool(ListAuditEntriesTool::class)` answers
    - **Gate:** a composer script `test:without-atrium` runs `vendor/bin/pest --configuration=phpunit.without-atrium.xml`, and `composer test` calls it after `@test:unit`.
    - **CI:** `.github/workflows/tests.yml` gains a `without-atrium` job that runs `composer remove --dev refactor-circus/atrium --no-update`, installs, and runs `composer test:without-atrium`. It is proven by the workflow run, not by `composer test`. A file check asserts the job exists and contains both commands.

    Done means `composer test` passes, including `test:without-atrium`.

## Design

**Config** (`config/keen.php`, new "Atrium history" banner under the Atrium section; read with a cast and a hard default each time):

```php
'atrium' => [
    'history' => [
        // Screens the History section attaches to: '*', '{package}.*' or exact keys.
        'screens' => ['*'],
        // The slot it renders in, on every screen that declares it.
        'slot' => 'after',
        // Screen-key patterns it never shows on (Str::is). Atrium's own
        // dashboard and settings screens are workspace chrome, not records.
        'exclude' => ['keen.*', 'atrium.*'],
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

A bad target or slot throws in local and testing and is reported once and skipped elsewhere (D-021); the banner comment says so.

`keen.ui.enabled` remains the master switch for everything Atrium. The `screens`, `slot` and `tabs` targets are read once at boot, because they are registry targets. `exclude` and `nav` are read each time they are evaluated. Tests that need other boot-time values flush the registry and call `HistoryExtensions::register()` again.

**Composer** (`composer.json`, and `composer.local.json` regenerated): adds `"conflict": {"refactor-circus/atrium": "<0.2.0"}`; adds the `test:without-atrium` script and calls it from `test`. Nothing else in `require`, `require-dev` or `suggest` changes.

**Classes** (all `final`, strict types, under `src/Atrium`):

- `HistoryExtensions`:
  - `registerIfSupported(string $atrium = Atrium::class): void` returns unless `method_exists($atrium, 'screens')`, then calls `register()`
  - `register(): void` reads the config, validates and normalises targets (criterion 9), and calls `Atrium::screens()->section()` and `->tab()`
  - the shared visibility check `visible(Request, ?Model $subject, Screen, bool $checkExclude): bool`
  - a private `invalid(InvalidArgumentException)` that throws in `local`/`testing` (or outside an application) and otherwise calls `report()`, mirroring Atrium's `ScreenExtensions::duplicate()`

  `KeenServiceProvider::boot()` calls `HistoryExtensions::registerIfSupported()` next to `registerAtriumPlugin()`, behind the same `class_exists` and `keen.ui.enabled` guard, using the class name as a string so the class is never loaded without Atrium.
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

**Route name** (D-027). The path stays `history/{package}` under Atrium's prefix, so URLs that users bookmarked keep working. The name is `atrium.keen.history.show`, not `atrium.history.show`, because:

- every Keen dashboard route already sits under Keen's `keen.` name group (`atrium.keen.entries.*`)
- a plugin defining names in Atrium's own `atrium.*` namespace would make Keen claim a name Atrium may reuse
- nothing is on Packagist, and the merge is grouped (Atrium D-009), so no caller outside the group breaks

Hosts never linked to the route by name except through `historyNavItem()`, which Atrium removes.

**Hidden tabs** (D-030). Keen adds no handling of its own: a `?tab=keen.activity` request for a viewer the tab's `authorize` refuses gets Atrium's 403.

**Standards.**

- architecture/dependencies: Atrium is referenced only from `src/Atrium`, behind `class_exists`; the `conflict` keeps pre-registry Atrium out.
- architecture/configuration: one config file, documented banner, casts and defaults on read.
- http/authorization: the host checks first, then `History::allows()`, which applies the package's own rule. For Roster that rule is `roster.audit.view`, so Keen needs no Roster permission.
- testing/test-layers: wiring is tested at the surface, with fixture hosts in Keen's suite.
- The atrium component rule (no CSS) is enforced by the existing `StylesTest`. It is not in Keen's standards index.

## Out of scope

- Removing the inline `<x-atrium::audit-trail>` and `historyNavItem()` calls in Roster, Showroom, Polycart, PennantPlus, Cortex and Impex. Each host spec does that.
- Roster's own `roster.organization` screen declaration and its `?tab=activity` fallback (Roster spec, D-005).
- A `history` slot on Keen's own screens (D-025).
- A scope filter on the history page. The scoped tab's "view all" link, like today's, carries only action and subject filters.
- Per-package nav sort overrides, per-screen slot overrides, and translated labels for registered tabs.
- Changing `AuditTrail`, `History` or `Package::audits()` in Keystone.
- Compatibility aliases or shims for the removed `atrium.history.show` route name, `x-atrium::audit-trail`, `x-atrium::audit.*` or `Plugin::historyNavItem()`.
- Changes to Atrium's own dashboard, widget or settings screens, other than excluding them through `exclude`.
- Any Atrium change, including its stylesheet safelist (not needed; see Risks).
- The API and MCP surfaces.
- A new permission or policy for History. It uses the package's `History` rule and Keen's existing `keen.policies`.

## Risks

- **Double checks.** The section's `authorize` and then the trail component both run `History::allows()`, which loads the subject again through `find()`. That is two small queries per audited detail screen. It is accepted so that `x-keen::audit-trail` stays safe when apps place it themselves. `PerformanceTest` must still pass.
- **Safelist.** Atrium PR #16 drops the `@source` lines for the audit components and `history/show`. Checked on 2026-10-29 against `public/atrium.css` in the Atrium PR worktree, and against a fresh `npm run build:css` of that branch (byte-identical output): `AtriumStyles::missingClasses()` over Atrium's removed `audit-trail`, `audit/entries`, `audit/changes` and `history/show` views returns `[]`. Every utility they use (`break-all`, `flex`, `flex-col`, `flex-wrap`, `gap-2`, `gap-3`, `gap-4`, `hover:underline`, `items-center`, `items-start`, `justify-end`, `mt-5`, `text-sm`, `underline-offset-2`, `w-40`, `w-48`, `w-56`) is covered by Atrium's `@source inline(...)` safelist, not by the removed sources, so it stays compiled. No Atrium follow-up is needed. If the builder adds a utility outside that safelist, `StylesTest` fails, and the builder switches to a safelisted one rather than shipping CSS.
- **Conflict version.** `<0.2.0` assumes Atrium's first tagged release with the registry is `0.2.0` or later; Atrium has no tags yet. If Atrium tags the registry under another number, the release that tags it updates Keen's constraint. Until then, the `method_exists` guard (criterion 2) covers any pre-registry install.
- **Open access.** With `keen.authorization` off, `KeenPlugin::authorizer()->can()` grants everything, so any `permission()` extension on Keen's screens shows to every viewer. This matches how Keen's own pages behave when authorization is off. Registrants that need their own check use `authorize()`.
- **Wildcard cost.** The section's closure runs on every declared screen. It returns early on `exclude` and on unknown or non-auditing packages before `History::allows()`; criteria 8 and 10 carry the query-count assertions.
- **Merge order.** This lands with Atrium PR #16 and the host specs. Criterion 6 asserts that `/atrium/history/shop` reaches `HistoryUiController` (not shadowed). Until a host removes its inline trail, a screen could show history twice (inline plus the section). Before Atrium PR #16 merges, Keen's `atrium/history/{package}` path would collide with Atrium's. Both are resolved by the grouped merge.

## Changelog

- D-020: criterion 1 (`conflict`), new criterion 20 (WithoutAtrium suite in `composer test`, plus the `without-atrium` CI lane), Goal and Design updated.
- D-021: criterion 9 settles bad targets and slots: throw `InvalidArgumentException` in local/testing; report once, skip and keep booting elsewhere. Tests for both environments.
- D-022, D-026: `exclude` defaults to `['keen.*', 'atrium.*']`; criterion 10 tests the shipped default and the `atrium.dashboard` outcome.
- D-023: nav items group by package label plus the `groups` map with `showroom` and `pennantplus` defaults (criterion 8).
- D-024: the nav label is "History" (criterion 8).
- D-025: Keen's screens declare the standard slots only, with `aside` on `keen.entry`; no `history` slot (criterion 13, Out of scope).
- D-027: route name `atrium.keen.history.show` confirmed (criterion 6, Design).
- D-028: folded into D-021. Its production behaviour (warn, skip, keep booting) is what D-021 does outside local/testing; in local/testing the suite rule (throw) applies.
- D-029: `conflict` on `refactor-circus/atrium <0.2.0` (criterion 1, Design, Risks).
- D-030: a hidden `keen.activity` tab requested with `?tab=` answers 403, Atrium's behaviour (criterion 12, Design).
- D-031: criterion 16 calls the reset hooks directly in one test.
- D-032: criterion 13 adds `NothingRegisteredTest`, a structural no-markup-change test independent of criterion 17.
- Review round 1 open items: all were waiting on Q1 to Q4 and the questions above, now settled by D-022 to D-032; "Risks and open questions" becomes "Risks". The safelist risk is now a checked result (no Atrium follow-up).
