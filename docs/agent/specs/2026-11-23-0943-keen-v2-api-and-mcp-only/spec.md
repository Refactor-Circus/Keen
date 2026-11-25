# Keen v2: API and MCP only

## Goal

Keen v2.0.0 is a JSON API plus MCP tools, and nothing else. Its Atrium plugin, Blade views and Atrium config are removed. Atrium v2 (and any other front end) reads the audit log through Keen's committed OpenAPI document and the generated TypeScript client `@refactor-circus/keen-client`. The new `GET {prefix}/packages` endpoint supplies the per-package History nav that the plugin used to build in PHP.

## Acceptance criteria

### The audited packages endpoint

1. `GET {keen.routes.prefix}/packages` (default `/api/keen/packages`), route `keen.packages.index`, answers 200 with `{"data": [{"key": string, "label": string}, ...]}`. Each item is a package from Keystone's `PackageRegistry` (`key`, `Package::$label`). The list is sorted by label, then key. `keen` itself is never listed, nor is the `app` source.
2. A package is listed only while `Package::audits()` is true. A fixture package with `{key}.audit.enabled` set to `false` is left out.
3. A package is listed only when `History::allows($package, $user, [])` is true for the caller. A package whose `authorizeHistory()` rule refuses the caller is left out, and the others stay. With `keen.authorization` on, a guest gets 401.
4. `ListAuditedPackagesAction::execute(?Authenticatable $viewer)` returns the listed packages (a `list<Package>`). It dispatches `AuditedPackagesListingActionEvent` and then `AuditedPackagesListedActionEvent`, which carries the listed keys. Both events implement `ActionStartingEvent`/`ActionFinishedEvent` and `ReadActionEvent`. Listing writes no audit entry.
5. MCP: `KeenServer::TOOLS` includes `ListAuditedPackagesTool`. It answers with the same items as structured content through `AuditedPackageResource`, its `outputSchema()` is `AuditedPackageResource::collectionSchema($schema)`, and `mcpTool()` validates the answer against that schema.
6. The existing API is unchanged: `keen.entries.index`, `keen.entries.store` and `keen.entries.show` keep their URIs, rules, statuses and payloads, and the current `SurfacesTest`, `PolicyTest` and RenamedTables API cases pass unchanged.

### The Atrium UI is gone

7. `src/Atrium/` (`KeenPlugin`, `HistoryExtensions`, `Http/Controllers/AuditUiController`, `HistoryUiController`) is deleted. `tests/ArchTest.php` asserts that `RefactorCircus\Keen` does not use `RefactorCircus\Atrium`, with no `ignoring()`. A test asserts that no file under `src/` or `config/` contains the case-sensitive string `Atrium`.
8. `resources/views/` (including `components/audit-trail.blade.php` and `components/audit/*.blade.php`) and `lang/` are deleted. `KeenServiceProvider` no longer calls `registerAtriumPlugin()`, `loadViewsFrom()` or `loadTranslationsFrom()`, and it registers no `keen-views` or `keen-lang` publish tag. A test asserts there is no `keen` view namespace or translation namespace, and that `ServiceProvider::publishableGroups()` lacks both tags.
9. Keen's registered routes named `keen.*` are exactly `keen.entries.index`, `keen.entries.store`, `keen.entries.show` and `keen.packages.index` (plus Keen's MCP route only when `keen.mcp.web.enabled` is true). No route named `atrium.keen.*` exists.
10. `config/keen.php` has no `ui` and no `atrium` key, and its banners do not mention Atrium. After boot, `config('keen.ui')` and `config('keen.atrium')` are null. Every other key keeps its v0.1 default.
11. `composer.json`: `refactor-circus/atrium` appears in none of `require`, `require-dev`, `suggest` or `conflict`. The Atrium VCS repository and any `extra.atrium` are removed, and there is no `test:without-atrium` script. `refactor-circus/keystone` is still the only `refactor-circus/*` package in `require`. The description says "JSON API and MCP" and no longer mentions Atrium screens. `tests/Feature/ComposerTest.php` checks all of this.
12. The Atrium-only tests and fixtures are deleted: `tests/Feature/Ui/`, `tests/Feature/StylesTest.php`, `tests/WithoutAtrium/`, `phpunit.without-atrium.xml`, `tests/Fixtures/Screens/`, and the `without-atrium` job in `.github/workflows/tests.yml`. `TestCase` boots without `AtriumServiceProvider` or Atrium's migrations, `tests/Pest.php` no longer uses `ResetsScreenExtensions`, and `parityGaps()` checks only the `http` and `mcp` surfaces.

### OpenAPI export

13. `dedoc/scramble` is in `require-dev` only. An arch rule asserts that `RefactorCircus\Keen` does not use `Dedoc\Scramble`.
14. `openapi.json` is committed at the repository root. It is an OpenAPI 3.1 document exported by Scramble against the Testbench workbench, and its `paths` are exactly `/entries` (get, post), `/entries/{entry}` (get) and `/packages` (get), relative to a server ending in `/api/keen`. `info.version` is `2.0.0`.
15. Each operation in `openapi.json` declares a 2xx `application/json` response whose schema reaches `components.schemas.AuditEntryResource` (entries) or `components.schemas.AuditedPackageResource` (`key`, `label`). The `GET /entries` operation lists the query parameters from `ListAuditEntriesData::rules()`, including `source`, `action`, `cursor` and `per_page`, and `POST /entries` has a request body with `action`. A test asserts all of this.
16. The Scramble configuration lives only in `Workbench\App\Providers\WorkbenchServiceProvider`, which `testbench.yaml` enables. Two exports in a row produce byte-identical files.

### TypeScript client

17. `client/package.json` names `@refactor-circus/keen-client` at version `2.0.0`, `"type": "module"`, with `exports` and `types` pointing at the committed `client/dist/index.js` and `client/dist/index.d.ts`. `openapi-fetch` is its only `dependency`. `openapi-typescript` and `typescript` are exact-pinned `devDependencies`. `client/package-lock.json` is committed, and `client/node_modules/` is git-ignored.
18. `client/src/schema.d.ts` is generated by `openapi-typescript` from `openapi.json`. `client/src/index.ts` exports `createKeenClient(options)`, which returns `createClient<paths>(options)` with `baseUrl` required, and also exports `KeenClient`, `paths`, `components`, `AuditEntry` and `AuditedPackage`. `client/dist/` holds the `tsc` build of `src/`.
19. `composer client:generate` exports `openapi.json` (`vendor/bin/testbench scramble:export --path=openapi.json`), runs `npm --prefix client ci` and `npm --prefix client run generate` (`openapi-typescript` and `tsc`), then writes `client/openapi.sha256` with `bin/client-stamp.php`. The stamp is the SHA-256 of `openapi.json` with line endings normalized to LF.
20. `tests/Feature/OpenApiTest.php`, which runs in `composer test:unit` with no Node, fails when a fresh Scramble export (to a temporary path) differs from the committed `openapi.json` as decoded JSON. It also fails when `client/openapi.sha256` does not match the committed `openapi.json`. Each failure message names `composer client:generate`.
21. `.github/workflows/tests.yml` has a `client` job. It sets up PHP and Node, runs `composer client:generate`, and fails on `git diff --exit-code -- openapi.json client`. `.gitattributes` does not `export-ignore` `openapi.json` or `client/`.

### Docs and release

22. The README describes Keen as a JSON API plus MCP. It has a section for `GET {prefix}/packages`, and an "OpenAPI and TypeScript client" section covering `openapi.json`, installing `@refactor-circus/keen-client` as a git dependency on the tag, a `createKeenClient({ baseUrl })` example and `composer client:generate`. It names Atrium v2 as the optional UI. The "History in Atrium" section is gone.
23. `CHANGELOG.md` under `## [Unreleased]` has `### Breaking` (every removed route, screen, component, config key, publish tag, translation and composer entry), `### Added` (the packages endpoint and MCP tool, `openapi.json`, the client) and `### Upgrading to v2.0.0`. The upgrade note points to Atrium v2 and its `atrium.integrations.keen.*` config for the UI.
24. The Boost skill (`resources/boost/skills/keen-development/SKILL.md`), `CLAUDE.md` and `AGENTS.md` describe Keen as API + MCP, with the packages endpoint, the OpenAPI/client workflow and the staleness test. The Atrium bullets in "Role in the suite" and the without-Atrium suite are removed.
25. `tests/Feature/DocsTest.php` asserts that the README, Boost skill, `CLAUDE.md` and `AGENTS.md` contain none of `keen.ui.enabled`, `keen.atrium`, `x-keen::`, `atrium.keen.history.show`, `registerAtriumPlugin` or `test:without-atrium`. It also asserts that the README mentions `keen.packages.index`, `openapi.json`, `@refactor-circus/keen-client` and `client:generate`.
26. `composer test` (analyse, lint check, 100% type coverage, unit) passes.

## Design

### Audited packages (new)

All new files are under `src/Domains/Audit/`:

- `Actions/ListAuditedPackagesAction.php`: `final`, extends Keystone `Action`, with `protected function handle(?Authenticatable $viewer = null): array` (`@return list<Package>`). It dispatches `AuditedPackagesListingActionEvent` (no payload), then calls a private `perform()`. `perform()` takes `PackageRegistry::all()` and keeps a package when its key is not `keen`, `$package->audits()` is true and `app(History::class)->allows($package, $viewer, [])` is true. It sorts the result by `label` then `key`. Finally `handle()` dispatches `AuditedPackagesListedActionEvent(list<string> $keys)` and returns. There is no transaction because it writes nothing. The action takes no Data because it has no input; the viewer is a further `handle()` parameter, like `ListAuditEntriesAction`'s `$about`.
- `Events/AuditedPackagesListingActionEvent.php` and `Events/AuditedPackagesListedActionEvent.php`: `final`, `Dispatchable` and `SerializesModels`, implementing `ActionStartingEvent`/`ActionFinishedEvent` plus `ReadActionEvent`, so `AuditRecorder` skips them.
- `Http/Resources/AuditedPackageResource.php`: `final`, extends Keystone `JsonResource`, `@mixin Package`, `toArray()` returns `['key' => ..., 'label' => ...]`, and `schema()` declares both as required strings.
- `Http/Requests/IndexAuditedPackageRequest.php`: `final`, extends Keystone `Request`, keeping the base `authorize()` (401 for guests under authorization). `persist()` calls `app(ListAuditedPackagesAction::class)->execute($this->user())` and returns `AuditedPackageResource::collection($packages)->response()`. It passes the raw user because `History::allows()` takes the raw user, as in Keystone's `ListHistoryRequest`.
- `Http/Controllers/AuditedPackageController.php`: `index(IndexAuditedPackageRequest $request): JsonResponse { return $request->persist(); }`.
- `routes.php`: `Route::get('packages', [AuditedPackageController::class, 'index'])->name('packages.index');`.
- `Mcp/Requests/ListAuditedPackagesMcpRequest.php` and `Mcp/Tools/ListAuditedPackagesTool.php`: a `#[Description]`, an empty input `schema()`, and `outputSchema()` as `AuditedPackageResource::collectionSchema($schema)`. The tool is added to `KeenServer::TOOLS`.

### Removal

- Delete `src/Atrium/**`, `resources/views/**` and `lang/**`.
- `KeenServiceProvider`: drop the `registerAtriumPlugin(KeenPlugin::class)` call, `registerHistoryExtensions()`, `loadViewsFrom()`, `loadTranslationsFrom()`, and the `keen-views` and `keen-lang` publishes. Keep config, migrations, policies, 404 responses, MCP and Cortex.
- `config/keen.php`: delete the "Atrium" (`ui`) and "Atrium history" (`atrium`) sections, and drop "and the dashboard" from the Authorization banner.
- Rewrite the `KeenAuditTrail` docblock so it no longer mentions Atrium.
- `composer.json`: remove Atrium from `require-dev`, `suggest`, `conflict` and `repositories`; remove the `test:without-atrium` script and its `@test:without-atrium` entry in `test`; update the description.
- Tests: delete the Atrium-only files listed in AC 12. Update `TestCase`, `tests/Pest.php` (`parityGaps()` without `ui`), `tests/ArchTest.php` and `tests/Feature/ComposerTest.php`. Add `tests/Feature/RemovedSurfacesTest.php` for AC 7 to 10.

### OpenAPI and client

- `composer require --dev dedoc/scramble`. `WorkbenchServiceProvider::register()` sets `scramble.api_path` to the `keen.routes.prefix` value, `app.url` to `http://localhost`, and `scramble.info.version` to `2.0.0`, with Keen's title and description. `testbench.yaml` uncomments the provider.
- `AuditController` and `AuditedPackageController` methods carry `@response` PHPDoc types naming their resource (a collection for index, a single resource for show and store), so Scramble documents payloads it cannot infer through `persist()`. If Scramble still names a component differently, a document transformer in the workbench provider fixes it. Nothing in `src/` imports Scramble.
- `client/`: `package.json`, `package-lock.json`, `tsconfig.json` (ES2022, `declaration`, `outDir: dist`, `rootDir: src`), `src/index.ts`, generated `src/schema.d.ts`, built `dist/` and `openapi.sha256`. `.gitignore` gains `/client/node_modules`.
- `bin/client-stamp.php`: writes `hash('sha256', str_replace("\r\n", "\n", file_get_contents('openapi.json')))` and a trailing newline to `client/openapi.sha256`.
- `composer.json` scripts gain `client:generate` (AC 19). `composer test` does not run it.
- `tests/Feature/OpenApiTest.php` registers Scramble's provider and the workbench provider, runs `scramble:export --path=<tmp>`, and compares the result with the committed file. It then checks the stamp and the shape in AC 14 and 15.
- A new `client` CI job (ubuntu, PHP 8.5, Node LTS) runs the generator and `git diff --exit-code`.

### Public API change

This is v2.0.0. The following are removed with no alias:

- the routes `atrium.keen.entries.*` and `atrium.keen.history.show`
- the screens `keen.entries`, `keen.entry` and `keen.history`
- the section `keen.history` and the tab `keen.activity`
- the components `x-keen::audit-trail`, `x-keen::audit.entries` and `x-keen::audit.changes`
- the classes `KeenPlugin` and `HistoryExtensions`
- the config `keen.ui.enabled` and `keen.atrium.*`
- the publish tags `keen-views` and `keen-lang`

Added: `keen.packages.index`, `list-audited-packages-tool`, `openapi.json` and `@refactor-circus/keen-client`.

## Out of scope

- Atrium v2's port of Keen's pages, nav, section and tab (`atrium.integrations.keen.*`), owned by `Atrium/2026-11-22-1823-atrium-v2-headless`.
- Keystone's removal of `registerAtriumPlugin()`, owned by `Keystone/2026-11-23-0943-drop-the-atrium-plugin-hook`, which this spec waits for. Widening the Keystone constraint after it tags is a release step (D-007).
- `ImportRosterAuditCommand`, the README's Roster section, `keen.cortex`/`registerCortex()`, and the recorded `atrium` surface value (D-014).
- Stats, search and settings-summary endpoints (D-002).
- Changing `keen.routes.enabled` or `keen.authorization` defaults (D-001).
- Publishing the client to an npm registry; it is consumed as a git dependency.
- Tagging the release and writing the GitHub release notes.

## Risks and open questions

- **Scramble inference.** Scramble may not follow `$request->persist()` into the resources, or may misread `Data::rules()`. The `@response` annotations and, if needed, a workbench document transformer cover this, and AC 15 makes any gap visible. Scramble support for PHP 8.5 and Laravel 13 must be checked at install. If no release supports them, the implementer stops and reports it, rather than pinning a dev branch.
- **Determinism.** Any non-deterministic Scramble output (ordering, the server URL from `app.url`) would make the staleness test flaky. AC 16 requires two identical exports, and the test compares decoded JSON, not bytes.
- **Git dependency from a subfolder.** A pnpm consumer of the client has to address `client/` inside the Keen repo (for example `github:Refactor-Circus/Keen#v2.0.0&path:/client`). npm does not support subfolder git dependencies. That is the consumer's concern, but `client/` must not be `export-ignore`d (AC 21).
- **Security default.** `keen.routes.enabled` is on and `keen.authorization` is off by default, as in v0.1, so a fresh install serves the log to anyone who reaches the API middleware. This is unchanged (D-001), but the README's security note must keep saying so.
- **Version jump.** v0.1.0 goes straight to v2.0.0 (D-015). Consumers on `^0.1` keep the Atrium UI until they move.
- **Keystone ordering.** Until Keystone's job merges, Keystone `dev-main` still has `registerAtriumPlugin()`. Keen simply stops calling it, so the build works either way.
