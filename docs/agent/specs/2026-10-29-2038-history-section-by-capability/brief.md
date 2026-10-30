Risk: high

## What changed

- **Atrium layer (`src/Atrium`)**: new `HistoryExtensions` registers a `keen.history` section on configurable wildcard targets and a scoped `keen.activity` tab per config entry. New `HistoryUiController` serves `GET /atrium/history/{package}` as `atrium.keen.history.show`. `KeenPlugin` adds a History nav item per audited package, a `screen()` factory with the access closure, and the history route. `AuditUiController` resolves screens for Keen's own pages.
- **Views and components**: `x-keen::audit-trail`, `audit.entries` and `audit.changes` are ported from Atrium. New views `ui/history`, `ui/history-section` and `ui/activity-tab`. `ui/index` and `ui/show` now use the screen layout (tabs, slots, row actions).
- **Config, composer, lang**: new `keen.atrium.history` config block, new translation keys, `conflict` on `refactor-circus/atrium <0.1.0`, and a `test:without-atrium` script that `composer test` calls.
- **Provider**: `KeenServiceProvider` calls `HistoryExtensions::registerIfSupported()` behind the existing Atrium guard.
- **CI, docs, tests**: new `without-atrium` CI job and `phpunit.without-atrium.xml` suite. README, Boost skill, CLAUDE.md/AGENTS.md and CHANGELOG are updated. Many new Pest tests, and `tests/Pest.php` applies `ResetsScreenExtensions`.

## Why

Atrium's screen registry (PR #16) removes its audit-specific history page, components and nav helper. Keen now owns all of that, so hosts declare no audit concept and name no Keen class. Keen still names no host package in code, and only `config/keen.php` does.

Decisions that shaped it:
- D-020: prove the no-Atrium case twice, in `composer test` and in a CI lane.
- D-021: a bad target or slot throws in local/testing, and is reported once and skipped elsewhere.
- D-022/D-026: `exclude` defaults to `['keen.*', 'atrium.*']`.
- D-023/D-024: History nav items group by package label plus a `groups` map, labelled "History".
- D-025: Keen's screens declare the standard slots only.
- D-027: route name `atrium.keen.history.show`.
- D-029: `conflict` on Atrium `<0.1.0`.
- D-030: a hidden `keen.activity` tab answers 403, as in Atrium.
- D-031: one test calls the reset hooks directly.
- D-032: a structural test shows the markup is unchanged when nothing is registered.

## Look at

- `src/Atrium/HistoryExtensions.php:67` (`register`): target normalisation, validation, section and tab registration.
- `src/Atrium/HistoryExtensions.php:108` (`visible`): the authorization order. A wrong order here either leaks history or runs extra queries on every screen.
- `src/Atrium/HistoryExtensions.php:226` (`invalid`): throws in local/testing and reports then skips elsewhere.
- `composer.json:40`: the new `conflict` and the `test:without-atrium` script.
- `src/Atrium/Http/Controllers/HistoryUiController.php:1`: validation, then authorization, then read. `source` must come from the route and not from the query string.

## Tested

Round 2 gate: PASS. `composer test` ran PHPStan, Pint, 100% type coverage, 198 Pest tests and 6 without-Atrium tests. `lint:check` and `ringside check` also passed.

- 1: `ComposerTest`
- 2: `ArchTest`, `RegistrationGuardTest`
- 3 and 4: `AuditComponentsTest`
- 5: `StylesTest`, `KeenStringsTest`
- 6, 7 and 15: `HistoryPageTest`
- 8: `HistoryNavigationTest`
- 9: `HistoryExtensionsTest`
- 10 and 11: `HistorySectionTest`
- 12: `ActivityTabTest`
- 13: `KeenScreensTest`, `NothingRegisteredTest`
- 14: `ScreenAccessTest`
- 16: `ResetsScreenExtensionsTest`
- 17: `SurfacesTest`, one forced change
- 18: `DocsTest`
- 19: `ArchTest`
- 20: `WithoutAtriumTest`

The CI `without-atrium` lane is proven only by the workflow run.

## Risks

- This is a public surface and config change: a new route name, new components, the `keen.atrium.history` config keys, and a composer `conflict`. It lands as one group with Atrium PR #16 and the host specs. Until the hosts remove their inline trails, a screen could show history twice.
- The `<0.1.0` constraint relies on Atrium's first tagged release (v0.1.0) carrying the registry. The `method_exists` guard covers untagged pre-registry installs.
- The wildcard section runs on every declared screen. Early exits and query-count tests bound the cost, and the section and the trail each run `History::allows()`, which costs two small queries per audited detail screen.
- With `keen.authorization` off, `permission()` extensions on Keen's screens show to everyone.
- The CI no-Atrium lane has not run yet. The in-process suite cannot see classmap leaks.
- No findings left (nits: 0).
