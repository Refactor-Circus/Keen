# Changelog

## [Unreleased](https://github.com/Refactor-Circus/Keen/compare/v0.1.0...main)

## [v0.1.0](https://github.com/Refactor-Circus/Keen/commits/v0.1.0) - 2026-10-30

### Breaking

- Moved to the Refactor Circus organisation: the package is now `refactor-circus/keen` with the PHP namespace `RefactorCircus\Keen` (it was `jayi/keen` and `JayI\Keen`). Update `composer.json` requirements and `use` statements. Old class names are not kept as aliases, so stored values written under them - polymorphic `*_type` columns, audit subjects, Pennant feature names - need updating to the new names.
- Actions take Data objects, not arrays: `ListAuditEntriesAction` takes a `ListAuditEntriesData` and `RecordAuditEventAction` a `RecordAuditEventData` (`Domains\Audit\Data`). Their validation moved from the Actions' `rules()` to the Data's, and the `AuditEntriesListing`/`AuditEntriesListed`/`AuditEventRecording` action events carry the Data. `Keen::record()` and the `AuditTrail` are unchanged.
- `list-audit-entries-tool` answers with the next cursor under `meta.next_cursor` (beside `meta.per_page` and `meta.prev_cursor`), not a top-level `next_cursor`.
- Keen now owns the history UI that refactor-circus/atrium removed with its screen registry. Each removed piece is replaced as follows; no aliases are kept:
  - the `atrium.history.show` route: `atrium.keen.history.show`, at the same path `/atrium/history/{package}`, so bookmarked URLs keep working
  - `<x-atrium::audit-trail>`: Keen's History section attaches itself to every package's Atrium screens, so hosts remove their inline trails. For an application's own views, use `<x-keen::audit-trail>` with the same props.
  - `<x-atrium::audit.entries>` and `<x-atrium::audit.changes>`: `<x-keen::audit.entries>` and `<x-keen::audit.changes>`
  - `Plugin::historyNavItem()`: Keen adds a "History" nav item for every package itself
- `composer.json` conflicts with `refactor-circus/atrium` `<0.1.0`, Atrium releases without the screen registry. Atrium stays a suggestion; `dev-main` installs are not affected.

### Added

- `keen.tables` names Keen's tables (`entries` => `keen_entries`, `chain` => `keen_chain`); rename them before migrating. `RefactorCircus\Keen\Support\Tables::name()` reads them, and a published config that lists only some keeps the defaults for the rest.
- `AuditLog::appendMany()` appends many entries in order with one chain lock and one bulk insert.
- `keen.policies` is documented as the place to swap `AuditEntryModel`'s policy; every model has one there.
- History in Atrium, with no declaration from the packages:
  - a history page per package: `GET /atrium/history/{package}`, route `atrium.keen.history.show`
  - a "History" nav item per auditing package, in the package's nav group
  - a `keen.history` History section on every Atrium screen. It shows the record's history on a detail screen and the package's on a list screen.
  - a `keen.activity` "Activity" tab on configured screens (by default `roster.organization`, listing what was recorded in the organization)

  All of them show only while the package audits and its history rule lets the viewer read it.
- The components `x-keen::audit-trail` (`source`, `subject`, `scope`, `action`, `limit`, `title`), `x-keen::audit.entries` (`entries`, `withoutSubject`, plus `screen` and `rows` for row sections) and `x-keen::audit.changes` (`changes`).
- Config `keen.atrium.history`:
  - `screens` (default `['*']`), `slot` (`after`), `exclude` (`['keen.*', 'atrium.*']`) and `tabs` (`['roster.organization' => ['scope' => true]]`)
  - `nav.enabled` (true), `nav.sort` (90), `nav.exclude` (`[]`) and `nav.groups` (Showroom's and PennantPlus's groups)

  A bad screen target or slot throws an `InvalidArgumentException` at boot in `local` and `testing`. Elsewhere it is reported once and skipped, and the application boots.
- Keen's Atrium pages declare the screens `keen.entries`, `keen.entry` and `keen.history`, so other packages can add tabs and sections to them.
- `composer test` runs `test:without-atrium`, which boots Keen with Atrium unloadable. A `without-atrium` CI job uninstalls Atrium and runs it too.

- Events implementing refactor-circus/keystone's `UnauditedActionEvent` (a per-row or per-page step of a larger job, such as one import row) are not recorded; the Actions the step runs still are.

- Records nothing for a package that turns its auditing off with its `{key}.audit.enabled` config key (refactor-circus/keystone's `Package::audits()`).
- The audit log for the Refactor Circus suite: every package's actions are recorded from refactor-circus/keystone's shared action events, with field changes, actor, subject, scope and surface, in an append-only hash chain.
- `Keen::record()` for the application's own events.
- A JSON API, an MCP server and an Atrium section for reading and recording entries, and the `AuditTrail` every package's history endpoint, tool and screens read through.
- `keen:verify`, `keen:prune` and `keen:import-roster`.
- `AuditEntryModel` fires a class-based event for every Eloquent hook (`AuditEntryCreatingEvent`, `AuditEntryCreatedEvent`, ... in `Domains\Audit\Events`, each a refactor-circus/keystone `ModelLifecycleEvent`). They are never recorded as audit entries themselves: recording one entry writes exactly one row.

### Changed

- An entry recorded inside a database transaction (`Keen::record()->save()`, `RecordAuditEventAction`, `AuditLog::append()`) is appended once the transaction commits, rather than locking the chain head until the surrounding transaction ends; until then the returned `AuditEntryModel` is unsaved (`exists` false, no id or hash) and it is filled in on commit. A rolled-back transaction writes no entry, as before.
- A person's own entries (`AuditEntryModel::about()`) are found as the ids of two indexed lookups (by actor, by subject) rather than one `actor OR subject` condition; a new migration replaces the `(subject_type, subject_id)` and `(actor_type, actor_id)` indexes with `(subject_type, subject_id, id)` and `(actor_type, actor_id, id)`, which serve the newest-first order and cursor too. Run `php artisan migrate`.
- `AuditEntryResource` asks the policy's `viewPersonalData` once per user and source per request rather than once per entry; a custom policy that decides per entry should decide by source.
- `keen:import-roster` appends in chunks with one chain lock and one bulk insert each (`AuditLog::appendMany()`), so imported entries fire no model events, and checks for an earlier import by Roster's first and last entries rather than scanning every entry's context.
- `AuditLog::verify()` reads plain rows rather than models; `Snapshots` builds its set of secret field names once (rebuilt when the hooks or `keen.redact` change); `AuditRecorder` keeps at most `AuditRecorder::MAX_SNAPSHOTS` (1000) snapshots of actions that have not finished, dropping the oldest, so a long-running command does not pile them up.
- Requires PHP 8.5 or later.
- MCP tools answer through `AuditEntryResource::toStructuredResponse()` (refactor-circus/keystone's resources) and declare their output schema; `AuditEntryResource` describes its fields with `schema()`.
- `RecordAuditEventAction` writes inside a database transaction, so `AuditEventRecordedActionEvent` fires only after the entry commits. The list and show action events implement refactor-circus/keystone's `ReadActionEvent`.
- A missing entry over the JSON API answers `{"message": "Not found."}`, and guests get 401 rather than 403 (refactor-circus/keystone's request base).
