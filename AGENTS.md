# Keen

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `refactor-circus/keen`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Role in the suite

- Keen is the audit log of the Refactor Circus suite, served as a JSON API plus MCP tools and nothing else: no screens, views, translations or UI config. No other package depends on it: it listens to `RefactorCircus\Keystone\Contracts\ActionStartingEvent` / `ActionFinishedEvent` and binds refactor-circus/keystone's `AuditTrail`, which every package reads history through.
- Package-specific knowledge (labels, snapshot extras, subject and scope pickers, context, redaction) comes from `RefactorCircus\Keystone\Audit\AuditHooks` or the `Auditable` event contract, never from Keen referencing another package.
- Code lives in `src/Domains/Audit` (actions, events, model, services, HTTP, MCP, commands, policy). Actions extend `RefactorCircus\Keystone\Actions\Action` with a starting and finished event each (read actions' events also implement `ReadActionEvent`; the recorder skips those and `UnauditedActionEvent` per-row steps) and do their writes inside `DB::transaction()`; requests extend Keystone's request bases. `AuditEntryModel` uses Keystone's `DispatchesModelEvents` with an `AuditEntry{Hook}Event` for every Eloquent hook; Keen's own action events and model events are never recorded, so one entry is one row. Keen keeps its own audit tables: it is the audit log.
- Tables: `Support\Tables` (on Keystone's `Database\Tables`, `keen.tables`: `entries`, `chain`) names every table. `AuditEntryModel::getTable()`, the migration, `AuditLog`'s chain head and `keen:import-roster` ask `Tables::name()`, never a literal `keen_*` name (`tests/ArchTest.php`, Keystone `Arch::literalTableNames()`); `tests/Modes/RenamedTables` reruns recording, the hash chain, verify, prune, the append-only guard, the API/MCP and the Roster import against renamed tables.
- The chain: `AuditLog::append()` defers to `afterCommit()`, so the chain head is locked only for the append itself, never for a caller's whole transaction (the returned entry is unsaved until then); bulk writes use `appendMany()` (one lock, one insert). `verify()` reads plain rows. Indexes added later go in a new migration with names from `Tables::name()`.
- Policies: every model has a policy in `keen.policies` (`AuditEntryModel` => `AuditEntryPolicy`, on Keystone's `Policies\Policy`), registered by `registerPolicies()`; an application swaps one by pointing the model at another class there. `tests/ArchTest.php` checks with `Arch::modelsWithoutPolicies()`.
- Action input is a `final readonly` `{Action}Data` in `Domains/Audit/Data` (on `RefactorCircus\Keystone\Data\Data`), never an array: fields are promoted constructor properties (camelCase of the input keys), `rules()` lives on the Data, and the starting/finished events that took input carry the Data. HTTP and MCP requests validate once: their `rules()` return `XData::rules()`, and `persist()`/`handle()` build with `XData::fromValidated($this->validated() / $validated)` - never `XData::from()` in a request (ArchTest). Untrusted input (`Keen::record()`) uses `XData::from()`, which validates; code that vouches for its values (`KeenAuditTrail`) uses `new XData(...)`. `ListAuditEntriesData::pagination()` (Keystone `Pagination::cursor()`) gives the list's rules, tool input schema and `meta`.
- `AuditEntryResource` extends `RefactorCircus\Keystone\Http\Resources\JsonResource` and declares `schema(JsonSchema $schema)` next to `toArray()` (always-present fields `required()`, conditional ones not, `nullable()`). MCP requests answer with `(new AuditEntryResource($entry))->toStructuredResponse()` / `AuditEntryResource::collection($page)->toStructuredResponse()`, and each tool declares `outputSchema()` as `AuditEntryResource::structuredSchema($schema)` / `collectionSchema($schema, ListAuditEntriesData::pagination())`. `mcpTool()` in tests checks every structured answer against the tool's schema (Keystone's `Testing\McpTools`), and `tests/Feature/Mcp/OutputSchemaTest.php` requires the declaration. `tests/ArchTest.php` checks Data input, action events, transactions, model events and parity with Keystone's `Testing\Arch` and `Testing\Parity`.
- The JSON API (`src/Domains/Audit/routes.php`, under `keen.routes.prefix`) is exactly `keen.entries.index`, `keen.entries.store`, `keen.entries.show` and `keen.packages.index`; `tests/Feature/RemovedSurfacesTest.php` holds the list, and that no `atrium.*` route, `keen` view or translation namespace, or `keen-views`/`keen-lang` publish tag exists.
- Audited packages: `GET {prefix}/packages` (`AuditedPackageController::index`, `IndexAuditedPackageRequest`) and `list-audited-packages-tool` (`ListAuditedPackagesTool`, `ListAuditedPackagesMcpRequest`) both run `ListAuditedPackagesAction::execute(?Authenticatable $viewer)` and answer through `AuditedPackageResource` (`key`, `label`). The action takes no Data: it keeps the `PackageRegistry` packages other than `keen` whose `audits()` is true and whose `History::allows($package, $viewer, [])` is true, sorted by label then key, and dispatches the `AuditedPackagesListing`/`Listed` `ReadActionEvent`s. Access is Keystone's base `Request::authorize()` (401 for guests under `keen.authorization`) plus each package's own history rule; there is no Keen ability for it.
- Keen knows nothing of Atrium: `tests/ArchTest.php` asserts `RefactorCircus\Keen` uses no `RefactorCircus\Atrium` (no `ignoring()`), `RemovedSurfacesTest` that no file in `src/` or `config/` contains `Atrium`, and `ComposerTest` that `composer.json` names no `refactor-circus/atrium`. Atrium v2 is the optional UI: it reads Keen through the API and the client, and its own `atrium.integrations.keen.*` config owns the pages, nav, section and tab. The recorded surface value `atrium` stays.
- OpenAPI and the client: `openapi.json` (repository root) is exported by Scramble (`dedoc/scramble`, `require-dev` only; `RefactorCircus\Keen` never uses `Dedoc\Scramble`, `tests/ArchTest.php`). Its only configuration is `workbench/app/Providers/WorkbenchServiceProvider.php` (enabled in `testbench.yaml`), which fixes the API path, `app.url` and `info.version` `2.0.0`, and replaces the resource components with each resource's `schema()`. Controllers carry `@response` PHPDoc types because `persist()` hides the resource from inference. `client/` is `@refactor-circus/keen-client` (`openapi-typescript` + `openapi-fetch`, `createKeenClient({ baseUrl })`), with `src/schema.d.ts`, `dist/` and `package-lock.json` committed.
- After changing a route, request rule, Data `rules()` or resource, run `composer client:generate` (needs Node): it exports `openapi.json`, runs `npm --prefix client ci` and `npm --prefix client run generate`, and writes `client/openapi.sha256` (`bin/client-stamp.php`, SHA-256 of the LF-normalized `openapi.json`). Commit all of it. The staleness test, `tests/Feature/OpenApiTest.php`, runs in `composer test` without Node and fails, naming `composer client:generate`, when a fresh export differs from `openapi.json` (as decoded JSON) or the stamp does not match. The `client` CI job regenerates and fails on `git diff --exit-code -- openapi.json client`; it is the authoritative check of the generated TypeScript.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`
- OpenAPI document and TypeScript client: `composer client:generate`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.

<!-- ring-master:start -->
## Ring Master

- Specs, product docs and local standards live in `docs/agent/`. Shared standards come from `vendor/` through `docs/agent/standards.index.yml`; read the matching standards before changing code.
- Workflow: `/ring-master-shape-spec` → `/ring-master-write-spec` → `/ring-master-create-tasks` → `/ring-master-implement` → `/ring-master-verify-spec`.
- Done means `composer test` passes. When work must break a standard, add a row to `docs/agent/standards-feedback.md`.
- Keep this checkout on `main`: other packages' jobs build against it. Never switch branches, commit or stash here; do any change that is not a factory job (which makes its own) in a worktree beside the package: `git worktree add ../<Package>--<slug> -b <branch> origin/main`, removed once it is merged.
<!-- ring-master:end -->
