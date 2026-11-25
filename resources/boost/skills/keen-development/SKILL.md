---
name: keen-development
description: >
  Record and read the Refactor Circus suite's audit log with refactor-circus/keen, a JSON API plus MCP tools: application
  events through Keen::record(), per-package history, the audited packages endpoint, the OpenAPI document and the
  @refactor-circus/keen-client TypeScript client, AuditHooks, authorization abilities, and the keen:verify / keen:prune commands.
license: MIT
metadata:
  author: Jay Fletcher
---

# Keen

Use this skill when a Laravel application uses `refactor-circus/keen`, the audit log of the Refactor Circus package suite. Keen is a JSON API plus MCP tools with no Blade screens; Atrium v2 is the optional UI. Keen ships its own Atrium front-end module (JS only, prebuilt in `dist/atrium/`, declared in `composer.json` `extra.atrium`), which Atrium v2 loads; it is configured under Atrium's `atrium.integrations.keen` keys.

## What Keen records on its own

- Every action of every installed suite package, from refactor-circus/keystone's shared action events. Reads are skipped. Model lifecycle events (`ModelLifecycleEvent`, including `AuditEntryModel`'s own `AuditEntry{Hook}Event`s) and Keen's own actions are not recorded.
- Each entry: `source` (package key or `app`), `action` (`product.updated`), actor, subject, optional scope, `surface` (`atrium`, `http`, `mcp`, `cortex`, `cli`, `code`), `changes` as `field => [old, new]`, `context`, and a hash chained to the previous entry.

## Record the application's own events

```php
use RefactorCircus\Keen\Facades\Keen;

Keen::record('invoice.paid')->on($invoice)->in($organization)->with(['amount' => 100])->save();
```

Action names are dotted and lower case. Application entries always have source `app`.

## Read history

- JSON (prefix `keen.routes.prefix`, default `api/keen`): `GET api/keen/entries` (filters `source`, `action`, `subject_type`, `subject_id`, `actor_type`, `actor_id`, `scope_type`, `scope_id`, `since`, `until`, `cursor`, `per_page`), `GET api/keen/entries/{id}`, `POST api/keen/entries`.
- Audited packages: `GET api/keen/packages` (route `keen.packages.index`) answers `{"data": [{"key", "label"}]}`, the registered packages that audit and whose history rule lets the caller read them, sorted by label then key; never `keen` or `app`. Use it to build a per-package History nav. With `keen.authorization` on, a guest gets 401.
- MCP: `list-audit-entries-tool`, `show-audit-entry-tool`, `record-audit-event-tool`, `list-audited-packages-tool`. Each declares an output schema; entries come under `data`, and listings carry `meta.next_cursor` to pass back as `cursor`.
- Actions take Data objects: `ListAuditEntriesAction` a `ListAuditEntriesData`, `RecordAuditEventAction` a `RecordAuditEventData` (`RefactorCircus\Keen\Domains\Audit\Data`). Build them with `XData::from($input)` for untrusted input (validates) or `new XData(...)`; prefer `Keen::record()` for the application's own events. `ListAuditedPackagesAction::execute($viewer)` takes no Data.
- Per package: `GET {package prefix}/history` and `list-{package}-history-tool`.
- Keen ships no Blade views or components. A UI reads the API; Atrium v2 is the optional one, and Keen's Atrium module (sources in the repository's `atrium/`, rebuilt with `pnpm build`) is what it shows.

## OpenAPI and TypeScript client

- `openapi.json` (repository root) is the OpenAPI 3.1 document of the JSON API, exported by Scramble against the Testbench workbench. Paths are relative to a server ending in `/api/keen`.
- `@refactor-circus/keen-client` (`client/`) is generated from it. Install it as a git dependency on the release tag (pnpm: `github:Refactor-Circus/Keen#v2.0.0&path:/client`; npm can't install a subfolder), then `createKeenClient({ baseUrl: 'https://app.test/api/keen' })` and call `GET('/entries')`, `GET('/packages')`. Types: `KeenClient`, `paths`, `components`, `AuditEntry`, `AuditedPackage`.
- After changing a route, request rule or resource, run `composer client:generate` (needs Node): it exports `openapi.json`, runs `npm --prefix client ci` and `npm --prefix client run generate`, and writes `client/openapi.sha256`. Commit all of it.
- The staleness test (`tests/Feature/OpenApiTest.php`, in `composer test`, no Node needed) fails when a fresh export differs from `openapi.json` or `client/openapi.sha256` doesn't match it; the `client` CI job fails when the regenerated client differs.

## Authorization

`keen.authorization` is off by default while the JSON API is on, so anyone who reaches the `api` middleware can read and record; turn it on (or guard `keen.routes.middleware`) before deploying. With `keen.authorization` on, define `viewAuditLog` (receives the source) and `recordAuditEvent` Gate abilities to restrict reading and recording. People always see entries they made or that are about them. To replace the rules, point `AuditEntryModel` at your own policy class in `keen.policies`.

## Tables

The log is `keen_entries` and the chain head `keen_chain`. Rename them under `keen.tables` (`entries`, `chain`) before migrating; in package code ask `RefactorCircus\Keen\Support\Tables::name('entries')`, never a literal name.

## Teach Keen about your own models

Use `RefactorCircus\Keystone\Audit\AuditHooks` (`label`, `snapshot`, `subject`, `context`, `scope`, `redact`), or implement `RefactorCircus\Keystone\Audit\Contracts\Auditable` on an action event.

## Operate

- `php artisan keen:verify` checks the hash chain.
- Schedule `php artisan keen:prune`; `keen.retention_days` sets the period (null keeps everything).
- `php artisan keen:import-roster` once, when moving from Roster's own audit log.