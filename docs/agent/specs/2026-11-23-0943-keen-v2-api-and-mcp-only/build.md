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

## Group 3

OpenAPI export with Scramble (criteria 13 to 16).

### Files

- `composer.json`: `dedoc/scramble` `^0.13.55` in `require-dev` only (v0.13.55 supports PHP `^8.1` and `illuminate/contracts` `^13.0`). `composer.lock` is not committed, so the install only changed the local `vendor/`.
- `workbench/app/Providers/WorkbenchServiceProvider.php`: the one Scramble configuration. `register()` fixes `app.url` to `http://localhost` and, in a `booting()` callback (so Keen's merged config is there whatever the provider order), sets `scramble.api_path` from `keen.routes.prefix`, `scramble.cache.store` to null (never serve a stale cached document), `scramble.ui.title` `Keen`, `scramble.info.version` `2.0.0` and a description. `boot()` adds a document transformer (see Deviations).
- `testbench.yaml`: enables `Workbench\App\Providers\WorkbenchServiceProvider`. Scramble's provider is auto-discovered as a dev package.
- `src/Domains/Audit/Http/Controllers/AuditController.php`: `@response AnonymousResourceCollection<CursorPaginator<AuditEntryResource>>` on `index` (adds the cursor `links` and `meta` that inference missed), `@response JsonResponse<ResourceResponse<AuditEntryResource>, 201>` on `store` (keeps the 201 and the `data` wrapper), `@response AuditEntryResource` on `show`, and a one-line summary for each.
- `src/Domains/Audit/Http/Controllers/AuditedPackageController.php`: `@response AnonymousResourceCollection<AuditedPackageResource>` on `index`, with a summary and a description.
- `openapi.json` (new): exported with `vendor/bin/testbench scramble:export --path=openapi.json`. It is OpenAPI 3.1.2, `info.version` `2.0.0`, with the server `http://localhost/api/keen` and the paths `/entries` (get, post), `/entries/{entry}` (get) and `/packages` (get).
- `tests/ArchTest.php`: "nothing uses Scramble, which only the workbench configures" (`RefactorCircus\Keen` does not use `Dedoc\Scramble`).
- `tests/Feature/OpenApiTest.php` (new).

### Tests

- `OpenApiTest` uses a file-local `ExportsOpenApi` trait that adds `ScrambleServiceProvider` and `WorkbenchServiceProvider` to the Feature `TestCase`'s providers. It checks that:
  - a fresh `scramble:export` to a temporary path equals the committed `openapi.json` as decoded JSON (the failure message names `composer client:generate`)
  - two exports in a row are byte-identical
  - the document is OpenAPI `3.1.x`, `info.version` is `2.0.0` and the title names Keen
  - every server URL ends in `/api/keen`
  - the operations are exactly `GET /entries`, `POST /entries`, `GET /entries/{entry}` and `GET /packages`
  - the 2xx `application/json` responses of the three entries operations reach `#/components/schemas/AuditEntryResource`, and `GET /packages` reaches `AuditedPackageResource`, whose properties and required fields are exactly `key` and `label` (`$ref`s are followed transitively)
  - `AuditEntryResource` has `id`, `source`, `action`, `hash` and `created_at`
  - `GET /entries` has the `source`, `action`, `cursor` and `per_page` query parameters
  - `POST /entries` has a JSON request body with an `action` property
  - `dedoc/scramble` is in `require-dev` and not in `require`
  - the document holds no machine-specific path, key or `file://` URL
- `tests/Feature/OpenApiTest.php` and `tests/ArchTest.php` pass (30 tests), and so do `composer analyse`, `composer lint:check` and `composer test:types` (100%). Per the job instructions, the full Pest suite is left to the gate.

### Deviations

- Task 3.4 allows a workbench document transformer "only if the components still come out wrong", and they did. Scramble infers resource fields through the model, and its result depended on whether the `keen_entries` table existed in the database it reads. In the CLI export (no table), `changes` came out as `string`. In the test (migrated database), it came out as `array|string`. The staleness test therefore failed on a fresh export. Even without that, the inferred `AuditEntryResource` was wrong in places: `changes` is an object, `actor.id` and `scope.type` are never null, and `context` came out as object-or-string. The transformer in `WorkbenchServiceProvider` replaces the `AuditEntryResource` and `AuditedPackageResource` components with each resource's own `schema()` (the JSON Schema their MCP tools already declare as output), serialized by `Illuminate\JsonSchema`. The document is now the same on every machine and matches the MCP output schemas. Nothing in `src/` changed for this.
- `scramble:export` still prints Scramble's `MD001` warning ("table `keen_entries` does not exist") when no workbench database is built. The warning is harmless because the transformer makes the components independent of the database. The export exits 0.
- For Group 4: the components declare `changes` and `context` as a bare `{"type": "object"}`. Depending on its options, `openapi-typescript` may render that as `Record<string, never>`. The generator flags in `client/package.json` should be chosen to give `Record<string, unknown>` (for example `--empty-objects-unknown`).
- `tests/Feature/OpenApiTest.php` also asserts criterion 13's "require-dev only", because `ComposerTest` belongs to Group 4's file list here.

### Retry

- Fixed: `vendor/bin/testbench scramble:export` (task 3.5, and Group 4's `client:generate`) could export a document with no `paths`. Testbench adds the root package's `extra.laravel` providers to its package manifest only when `package:discover` runs from the Testbench CLI, and a manifest written outside the CLI leaves `KeenServiceProvider` out. When that happened, the CLI app had no `keen.*` routes and no `keen` config. `testbench.yaml` now lists `RefactorCircus\Keen\KeenServiceProvider` before `WorkbenchServiceProvider`. Laravel skips a provider that is already registered, so the entry is harmless when the manifest has it too. The tests do not use `WithWorkbench`, so they are unaffected.
- Checked: with Keen's entry removed from the manifest, a CLI export now equals the committed `openapi.json` byte for byte, and so does an export with a fresh manifest. `openapi.json` is unchanged. `OpenApiTest` and `ArchTest` pass (30 tests), and `composer lint:check` and `composer analyse` pass.

## Group 4

Generated TypeScript client and staleness check (criteria 17 to 21).

### Files

- `client/package.json` (new): `@refactor-circus/keen-client` `2.0.0`, `"type": "module"`, `types` `./dist/index.d.ts`, `exports["."]` `{types: ./dist/index.d.ts, import: ./dist/index.js}`, `files` `dist` and `src`. `openapi-fetch` `^0.17.0` is the only dependency. `openapi-typescript` `7.13.0` and `typescript` `5.9.3` are exact-pinned devDependencies (`typescript` 7 is out, but `openapi-typescript` 7.13 has the peer `typescript ^5.x`, so `npm ci` would refuse it). `generate` runs `openapi-typescript ../openapi.json -o src/schema.d.ts --empty-objects-unknown && tsc -p tsconfig.json`.
- `client/tsconfig.json` (new): ES2022 target and lib (plus DOM for fetch's types), `NodeNext` modules, `strict`, `declaration`, `rootDir: src`, `outDir: dist`, `newLine: lf`.
- `client/src/index.ts` (new): `createKeenClient(options: ClientOptions & { baseUrl: string }): KeenClient`, which returns `createClient<paths>(options)`, plus `KeenClient` (`Client<paths>`), the re-exported `paths` and `components` types, and the `AuditEntry` and `AuditedPackage` aliases of the two resource schemas.
- `client/src/schema.d.ts`, `client/dist/index.js`, `client/dist/index.d.ts`, `client/package-lock.json`, `client/openapi.sha256` (new, generated by `composer client:generate`).
- `bin/client-stamp.php` (new): writes `hash('sha256', <openapi.json with CRLF turned into LF>)` and a newline to `client/openapi.sha256`, and exits 1 when `openapi.json` is missing.
- `composer.json`: the `client:generate` script (`@php vendor/bin/testbench scramble:export --path=openapi.json`, `npm --prefix client ci`, `npm --prefix client run generate`, `@php bin/client-stamp.php`). `test` does not run it.
- `.gitignore`: `/client/node_modules`.
- `.github/workflows/tests.yml`: a `client` job (ubuntu, PHP 8.5 through the same pinned `setup-php`, `actions/setup-node@v7` with `lts/*`, `composer install`, `composer client:generate`, then `git diff --exit-code -- openapi.json client`).
- `.gitattributes`: unchanged. It already export-ignores neither `client/` nor `openapi.json`, and `ComposerTest` now holds that.
- `tests/Feature/OpenApiTest.php`, `tests/Feature/ComposerTest.php`.

### Tests

- `OpenApiTest` checks that:
  - `client/openapi.sha256` equals the SHA-256 of the LF-normalized committed `openapi.json` (the message names `composer client:generate`)
  - `src/schema.d.ts`, `src/index.ts`, `dist/index.js`, `dist/index.d.ts`, `package-lock.json` and `tsconfig.json` exist under `client/`
  - `package.json` has the name, version, `type`, `types` and `exports` above
  - `openapi-fetch` is the only dependency, and `openapi-typescript` and `typescript` are pinned to exact `x.y.z` versions
  - the `generate` script is the one above
  - `changes` and `context` are not typed `Record<string, never>` in the schema
  - both `src/index.ts` and `dist/index.d.ts` export `createKeenClient`, `KeenClient`, `AuditEntry`, `AuditedPackage`, `paths` and `components`
  - `.gitignore` lists `/client/node_modules`
- `ComposerTest` checks that:
  - `client:generate` is exactly the four steps, in order, and `bin/client-stamp.php` exists
  - neither `test` nor `test:unit` runs `@client:generate`
  - the workflow has a `client` job that sets up PHP 8.5 and Node `lts/*`, runs `composer install`, and runs `composer client:generate` before `git diff --exit-code -- openapi.json client`. The job is read as text, so the test needs no YAML parser.
  - `.gitattributes` export-ignores none of `client`, `client/dist` and `openapi.json`
- `composer client:generate` ran twice. Both runs left `openapi.json` unchanged and wrote the same stamp (`33a598be…`). `composer analyse` and `pint --test` on the touched PHP files pass. Per the job instructions, the Pest suite is left to the gate.

### Deviations

- `generate` adds `--empty-objects-unknown` to the command in task 4.2. Without it, `openapi-typescript` typed `AuditEntry.changes` and `context` (bare `{"type": "object"}` in the document) as `Record<string, never>`, so consumers could not read them. Group 3 flagged this. They are now `Record<string, unknown>`.
- `src/index.ts` imports and re-exports the schema types from `'../src/schema.js'` instead of `'./schema.js'`. `tsc` emits nothing for a `.d.ts` input, so `dist/` never gets a `schema.d.ts`. With `./schema.js`, `dist/index.d.ts` would point at a missing file. The `../src/` path resolves to `src/schema.d.ts` from both `src/` and `dist/`, and `src/` ships with the package. This keeps the task's `generate` command with no copy step.
- `actions/setup-node` is referenced as `@v7`, as in Atrium's browser-tests workflow, and not by commit SHA like the other actions in this workflow. No pinned SHA for it exists in the suite to copy.
