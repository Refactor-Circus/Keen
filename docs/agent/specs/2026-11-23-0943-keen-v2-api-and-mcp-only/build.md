# Build: Keen v2: API and MCP only

## Group 1

Audited packages endpoint and MCP tool (criteria 1 to 6).

### Files

- `src/Domains/Audit/Actions/ListAuditedPackagesAction.php` (new): `handle(?Authenticatable $viewer = null): array` (`list<Package>`). It keeps the `PackageRegistry` packages that are not `keen`, where `audits()` is true and `History::allows($package, $viewer, [])` is true. It sorts them by label, then key, with `strcmp`. It has no transaction and writes nothing.
- `src/Domains/Audit/Events/AuditedPackagesListingActionEvent.php` (new, no payload) and `AuditedPackagesListedActionEvent.php` (new, `list<string> $keys`): `ActionStartingEvent`/`ActionFinishedEvent` plus `ReadActionEvent`.
- `src/Domains/Audit/Http/Resources/AuditedPackageResource.php` (new): `final`, `@mixin Package`, `{key, label}`, and `schema()` with both fields required.
- `src/Domains/Audit/Http/Requests/IndexAuditedPackageRequest.php`, `src/Domains/Audit/Http/Controllers/AuditedPackageController.php` (new); `src/Domains/Audit/routes.php` adds `GET packages` as `packages.index`.
- `src/Domains/Audit/Mcp/Requests/ListAuditedPackagesMcpRequest.php`, `src/Domains/Audit/Mcp/Tools/ListAuditedPackagesTool.php` (new): empty input schema, and `outputSchema()` is `AuditedPackageResource::collectionSchema($schema)`. `src/Mcp/KeenServer.php` adds the tool to `TOOLS`.
- `tests/Pest.php`: `parityGaps()` exempts `ListAuditedPackagesAction` from the `ui` surface only.
- `tests/Feature/Mcp/OutputSchemaTest.php`: the count of structured tools goes from 3 to 4 (see Deviations).

### Tests

- `tests/Feature/AuditedPackagesActionsTest.php` checks that the action:
  - returns `Package`s sorted by label, then key
  - never lists `keen`
  - leaves out a package with `{key}.audit.enabled` false
  - leaves out a package whose `authorizeHistory()` refuses the viewer, keeps the rest, and still lists that package for a viewer the rule allows
  - dispatches the Listing event, then the Listed event (both are `ReadActionEvent`s, and Listed carries the keys)
  - records no audit entry
- `tests/Feature/Http/AuditedPackagesTest.php`, by route name: `keen.packages.index` answers 200 with exactly `{"data": [{key, label}, ...]}`, and 401 for a guest when `keen.authorization` is on.
- `tests/Feature/Mcp/ListAuditedPackagesToolTest.php`: `mcpTool()` returns the same items as structured content and passes the output schema check, and the tool is in `KeenServer::TOOLS`.
- `composer analyse` passes. Per the job instructions, the Pest suite is left to the gate.
- Retry check: no code changed. The new action, HTTP and MCP test files, `tests/Feature/Mcp` and `tests/ArchTest.php` (parity included) pass, and so do `composer lint:check`, `composer analyse` and `composer test:types` (100%).

### Deviations

- `ShopServiceProvider` is unchanged. Tests that need extra packages (for sorting, refusal and turned-off auditing) register them in `PackageRegistry` inside the test. This follows the task's "only if needed".
- `tests/Feature/Mcp/OutputSchemaTest.php` is not in the group's file list, and task 1.5 asks for it to pass unchanged. It asserts exactly 3 structured tools, though, and the new tool (which criterion 5 requires) makes 4, so the count is now 4. Nothing else in that test changed. `SurfacesTest` and `PolicyTest` are untouched.
- While Atrium is still installed (until Group 2), signed-in viewers also see `atrium`, because its package authorizes them. So the refusal case checks with `toContain`/`not->toContain` rather than an exact list. The exact-list cases run as a guest, which never sees `atrium`, so they hold both before and after Group 2.

## Group 2

Removed the Atrium plugin, views, config and the Atrium-only tests (criteria 7 to 12).

### Files

- Deleted: `src/Atrium/` (`KeenPlugin`, `HistoryExtensions`, `Http/Controllers/AuditUiController`, `HistoryUiController`), `resources/views/`, `lang/`, `tests/Feature/Ui/`, `tests/Feature/StylesTest.php`, `tests/WithoutAtrium/`, `tests/Fixtures/Screens/` and `phpunit.without-atrium.xml`.
- `src/KeenServiceProvider.php`: no `registerAtriumPlugin()`, `registerHistoryExtensions()`, `loadViewsFrom()` or `loadTranslationsFrom()`, and no `keen-views`/`keen-lang` publishes. It keeps config, migrations, policies, 404 responses, MCP and Cortex.
- `src/Domains/Audit/Services/KeenAuditTrail.php`: the docblock no longer mentions Atrium.
- `config/keen.php`: the `ui` and `atrium` sections are gone, and the Authorization banner no longer says "and the dashboard".
- `composer.json`: Atrium is gone from `require-dev`, `suggest`, `conflict` and `repositories`. The `test:without-atrium` script and its entry in `test` are removed. The description now reads "... served as a JSON API and MCP tools." `composer update` ran in the worktree. `composer.lock` is not committed, so this only changed the local `vendor/`.
- `.github/workflows/tests.yml`: the `without-atrium` job is removed.
- `tests/TestCase.php`: no `AtriumServiceProvider` and no Atrium migrations.
- `tests/Pest.php`: no `ResetsScreenExtensions`. `parityGaps()` checks only `http` and `mcp`, and Group 1's exemption is gone. The `RebootsWithConfig` example now uses `keen.mcp.web.enabled`.
- `tests/ArchTest.php`: the "nothing uses Atrium" rule replaces "only the Atrium layer uses Atrium", with no `ignoring()`. The parity rule is renamed "every action is reachable from the HTTP API and MCP".
- `tests/Feature/ComposerTest.php`: rewritten for criterion 11. The `without-atrium` CI assertions are removed.
- `tests/Feature/RemovedSurfacesTest.php` (new).
- `tests/Feature/SurfacesTest.php`: two Atrium cases removed (see Deviations).

### Tests

- `RemovedSurfacesTest` checks that:
  - no file under `src/` or `config/keen.php` contains `Atrium` (case-sensitive)
  - `src/Atrium`, `resources/views` and `lang` do not exist
  - there is no `keen` view hint or translation namespace
  - `publishableGroups()` has `keen-config` and `keen-migrations`, but not `keen-views` or `keen-lang`
  - the `keen.*` route names are exactly the four API routes, and there is no `atrium.*` route and no `mcp/keen` route by default
  - after rebooting with `keen.mcp.web.enabled` true, the `POST mcp/keen` route exists and the `keen.*` names are unchanged (laravel/mcp leaves the web route unnamed)
  - `config('keen.ui')` and `config('keen.atrium')` are null, and the top-level keys are exactly the v0.1 ones minus `ui` and `atrium`
- `ComposerTest` checks that:
  - keystone is the only suite package in `require`
  - Atrium appears in none of `require`, `require-dev`, `suggest` or `conflict`
  - there is no Atrium repository and no `extra.atrium`
  - there is no `test:without-atrium` script or entry, no `phpunit.without-atrium.xml`, and no `without-atrium` in the workflow
  - the description contains "JSON API and MCP" and not "Atrium"
- `composer analyse` and `pint --test` pass. Per the job instructions, the Pest suite is left to the gate.
- Retry check: no code changed. `src/`, `config/`, `composer.json`, the workflow and `tests/` hold no Atrium wiring (only the recorded `atrium` surface value stays, per D-014). `vendor/refactor-circus` holds only keystone, ringside and standards. `RemovedSurfacesTest`, `ComposerTest`, `ArchTest` and `SurfacesTest` pass, and so do `composer analyse` and `composer lint:check`.

### Deviations

- `tests/Feature/SurfacesTest.php` is not in the group's file list. Two of its cases tested the Atrium UI: "gives the audit log its own section in atrium" (`/atrium/keen/entries`) and "shows each package's history in its own atrium screens" (`x-keen::audit-trail`). The routes and components they test are removed here, so those cases are deleted, along with their unused imports. The API, MCP, policy and trail cases are unchanged, which matches criterion 6's "API cases pass unchanged".
- `composer update` resolved `refactor-circus/keystone` to the `v0.1.1` tag instead of the `dev-main 3b48908` the worktree had before, because `require` is `^0.1` (D-007) and `prefer-stable` is set. `composer analyse` passes against it.
