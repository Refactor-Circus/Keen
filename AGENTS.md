# Keen

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `refactor-circus/keen`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Role in the suite

- Keen is the audit log of the Refactor Circus suite. No other package depends on it: it listens to `RefactorCircus\Keystone\Contracts\ActionStartingEvent` / `ActionFinishedEvent` and binds refactor-circus/keystone's `AuditTrail`, which every package and Atrium read history through.
- Package-specific knowledge (labels, snapshot extras, subject and scope pickers, context, redaction) comes from `RefactorCircus\Keystone\Audit\AuditHooks` or the `Auditable` event contract, never from Keen referencing another package.
- Code lives in `src/Domains/Audit` (actions, events, model, services, HTTP, MCP, commands, policy). Actions extend `RefactorCircus\Keystone\Actions\Action` with a starting and finished event each (read actions' events also implement `ReadActionEvent`; the recorder skips those and `UnauditedActionEvent` per-row steps) and do their writes inside `DB::transaction()`; requests extend Keystone's request bases. `AuditEntryModel` uses Keystone's `DispatchesModelEvents` with an `AuditEntry{Hook}Event` for every Eloquent hook; Keen's own action events and model events are never recorded, so one entry is one row. Keen keeps its own audit tables: it is the audit log.
- Tables: `Support\Tables` (on Keystone's `Database\Tables`, `keen.tables`: `entries`, `chain`) names every table. `AuditEntryModel::getTable()`, the migration, `AuditLog`'s chain head and `keen:import-roster` ask `Tables::name()`, never a literal `keen_*` name (`tests/ArchTest.php`, Keystone `Arch::literalTableNames()`); `tests/Modes/RenamedTables` reruns recording, the hash chain, verify, prune, the append-only guard, the API/MCP and the Roster import against renamed tables.
- The chain: `AuditLog::append()` defers to `afterCommit()`, so the chain head is locked only for the append itself, never for a caller's whole transaction (the returned entry is unsaved until then); bulk writes use `appendMany()` (one lock, one insert). `verify()` reads plain rows. Indexes added later go in a new migration with names from `Tables::name()`.
- The chain: `AuditLog::append()` defers to `afterCommit()`, so the chain head is locked only for the append itself, never for a caller's whole transaction (the returned entry is unsaved until then); bulk writes use `appendMany()` (one lock, one insert). `verify()` reads plain rows. Indexes added later go in a new migration with names from `Tables::name()`.
- Policies: every model has a policy in `keen.policies` (`AuditEntryModel` => `AuditEntryPolicy`, on Keystone's `Policies\Policy`), registered by `registerPolicies()`; an application swaps one by pointing the model at another class there. `tests/ArchTest.php` checks with `Arch::modelsWithoutPolicies()`.
- Action input is a `final readonly` `{Action}Data` in `Domains/Audit/Data` (on `RefactorCircus\Keystone\Data\Data`), never an array: fields are promoted constructor properties (camelCase of the input keys), `rules()` lives on the Data, and the starting/finished events that took input carry the Data. HTTP and MCP requests validate once: their `rules()` return `XData::rules()`, and `persist()`/`handle()` build with `XData::fromValidated($this->validated() / $validated)` - never `XData::from()` in a request (ArchTest). Atrium controllers and other untrusted input (`Keen::record()`) use `XData::from()`, which validates; code that vouches for its values (`KeenAuditTrail`) uses `new XData(...)`. `ListAuditEntriesData::pagination()` (Keystone `Pagination::cursor()`) gives the list's rules, tool input schema and `meta`.
- `AuditEntryResource` extends `RefactorCircus\Keystone\Http\Resources\JsonResource` and declares `schema(JsonSchema $schema)` next to `toArray()` (always-present fields `required()`, conditional ones not, `nullable()`). MCP requests answer with `(new AuditEntryResource($entry))->toStructuredResponse()` / `AuditEntryResource::collection($page)->toStructuredResponse()`, and each tool declares `outputSchema()` as `AuditEntryResource::structuredSchema($schema)` / `collectionSchema($schema, ListAuditEntriesData::pagination())`. `mcpTool()` in tests checks every structured answer against the tool's schema (Keystone's `Testing\McpTools`), and `tests/Feature/Mcp/OutputSchemaTest.php` requires the declaration. `tests/ArchTest.php` checks Data input, action events, transactions, model events and parity with Keystone's `Testing\Arch` and `Testing\Parity`.
- Atrium screens use only `x-atrium::*` components and Atrium's safelisted utilities; `tests/Feature/StylesTest.php` enforces it. Keen ships no stylesheet.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

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
<!-- ring-master:end -->
