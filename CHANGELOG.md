# Changelog

## [Unreleased](https://github.com/Refactor-Circus/Keen/commits/main)

### Breaking

- Moved to the Refactor Circus organisation: the package is now `refactor-circus/keen` with the PHP namespace `RefactorCircus\Keen` (it was `jayi/keen` and `JayI\Keen`). Update `composer.json` requirements and `use` statements. Old class names are not kept as aliases, so stored values written under them - polymorphic `*_type` columns, audit subjects, Pennant feature names - need updating to the new names.

### Added

- The audit log for the Refactor Circus suite: every package's actions are recorded from refactor-circus/keystone's shared action events, with field changes, actor, subject, scope and surface, in an append-only hash chain.
- `Keen::record()` for the application's own events.
- A JSON API, an MCP server and an Atrium section for reading and recording entries, and the `AuditTrail` every package's history endpoint, tool and screens read through.
- `keen:verify`, `keen:prune` and `keen:import-roster`.

### Changed

- Requires PHP 8.5 or later.
