---
name: keen-development
description: >
  Record and read the Refactor Circus suite's audit log with refactor-circus/keen: application events through Keen::record(),
  per-package history, AuditHooks, authorization abilities, and the keen:verify / keen:prune commands.
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
- Blade: `<x-atrium::audit-trail source="showroom" :subject="$product" />`.

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
