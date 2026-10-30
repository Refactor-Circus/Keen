---
name: keen-development
description: >
  Record and read the Refactor Circus suite's audit log with refactor-circus/keen: application events through Keen::record(),
  per-package history, the Atrium History page, nav items, section and Activity tab, AuditHooks, authorization abilities,
  and the keen:verify / keen:prune commands.
license: MIT
metadata:
  author: Jay Fletcher
---

# Keen

Use this skill when a Laravel application uses `refactor-circus/keen`, the audit log of the Refactor Circus package suite.

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

- JSON: `GET api/keen/entries` (filters `source`, `action`, `subject_type`, `subject_id`, `actor_type`, `actor_id`, `scope_type`, `scope_id`, `since`, `until`, `cursor`, `per_page`), `GET api/keen/entries/{id}`.
- MCP: `list-audit-entries-tool`, `show-audit-entry-tool`, `record-audit-event-tool`. Each declares an output schema; entries come under `data`, and listings carry `meta.next_cursor` to pass back as `cursor`.
- Actions take Data objects: `ListAuditEntriesAction` a `ListAuditEntriesData`, `RecordAuditEventAction` a `RecordAuditEventData` (`RefactorCircus\Keen\Domains\Audit\Data`). Build them with `XData::from($input)` for untrusted input (validates) or `new XData(...)`; prefer `Keen::record()` for the application's own events.
- Per package: `GET {package prefix}/history` and `list-{package}-history-tool`.
- Blade, in the application's own views: `<x-keen::audit-trail source="showroom" :subject="$product" />` (props `source`, `subject`, `scope`, `action`, `limit`, `title`; also `x-keen::audit.entries` and `x-keen::audit.changes`). It renders nothing while the package doesn't audit or its history rule refuses the viewer.

## History in Atrium

Atrium is a `suggest`, never a requirement. With Atrium installed and `keen.ui.enabled` on, Keen attaches history itself; never add a trail component, history route or history nav item to a package's Atrium screens.

- History page: `GET /atrium/history/{package}`, route `atrium.keen.history.show` (filters `action`, `subject_type`, `subject_id`, `per_page`, `cursor`). 404 for an unknown or non-auditing package, 403 when the package's history rule refuses.
- Nav: one "History" item per registered package except `keen`, in the package's nav group, shown only while the package audits and its history rule allows the viewer.
- Section: `keen.history`, on every screen (`*`) in the `after` slot. A detail screen shows its record's history; a list screen, or a detail screen without a record, the package's. Hidden on `keen.*` and `atrium.*` screens, for non-auditing packages and for viewers the history rule refuses. The subject's type is its morph class, so an unmapped model puts its class name into the "view all" link; a record that isn't an audited subject of the package shows an empty history.
- Tab: `keen.activity` ("Activity") on each `keen.atrium.history.tabs` target. `['scope' => true]` lists entries recorded in the screen's record (`->in($organization)`); `['scope' => false]` the record's own history. A refused viewer doesn't see it and gets 403 for `?tab=keen.activity`.
- Config `keen.atrium.history`: `screens` (default `['*']`; `*`, `{package}.*` or screen keys), `slot` (`after`), `exclude` (`['keen.*', 'atrium.*']`, `Str::is` patterns), `tabs` (`['roster.organization' => ['scope' => true]]`), `nav.enabled`, `nav.sort` (90), `nav.exclude` (package keys), `nav.groups` (package key => group). `screens`, `slot` and `tabs` are read at boot.
- A bad screen target or slot throws `InvalidArgumentException` at boot in `local` and `testing`; elsewhere it is reported once and skipped, and boot continues (a bad slot skips the section, not the tabs).
- Keen conflicts with `refactor-circus/atrium` below `0.1.0` (no screen registry); an older Atrium installed anyway gets no section or tabs.

## Authorization

With `keen.authorization` on, define `viewAuditLog` (receives the source) and `recordAuditEvent` Gate abilities to restrict reading and recording. People always see entries they made or that are about them. To replace the rules, point `AuditEntryModel` at your own policy class in `keen.policies`.

## Tables

The log is `keen_entries` and the chain head `keen_chain`. Rename them under `keen.tables` (`entries`, `chain`) before migrating; in package code ask `RefactorCircus\Keen\Support\Tables::name('entries')`, never a literal name.

## Teach Keen about your own models

Use `RefactorCircus\Keystone\Audit\AuditHooks` (`label`, `snapshot`, `subject`, `context`, `scope`, `redact`), or implement `RefactorCircus\Keystone\Audit\Contracts\Auditable` on an action event.

## Operate

- `php artisan keen:verify` checks the hash chain.
- Schedule `php artisan keen:prune`; `keen.retention_days` sets the period (null keeps everything).
- `php artisan keen:import-roster` once, when moving from Roster's own audit log.
- `composer test` includes `composer test:without-atrium` (Keen booted with Atrium unloadable in-process); the `without-atrium` CI job, which uninstalls Atrium first, is the authoritative proof that Keen runs without it.
