# Changelog

## [Unreleased](https://github.com/jayjfletcher/Keen/commits/main)

### Added

- The audit log for the jayi suite: every package's actions are recorded from jayi/foundation's shared action events, with field changes, actor, subject, scope and surface, in an append-only hash chain.
- `Keen::record()` for the application's own events.
- A JSON API, an MCP server and an Atrium section for reading and recording entries, and the `AuditTrail` every package's history endpoint, tool and screens read through.
- `keen:verify`, `keen:prune` and `keen:import-roster`.
