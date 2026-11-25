Risk: high

## What changed

- Removed the Atrium UI: `src/Atrium/`, `resources/views/`, `lang/`, the `keen-views` and `keen-lang` publish tags, the `keen.ui` and `keen.atrium` config, and the Atrium entries in `composer.json`.
- Added `GET {prefix}/packages` (`keen.packages.index`) and the `list-audited-packages-tool` MCP tool. Both run `ListAuditedPackagesAction` and answer through `AuditedPackageResource`.
- Added `openapi.json`, exported by Scramble (`require-dev` only, configured in the workbench provider), and the `@refactor-circus/keen-client` TypeScript client in `client/`, built by `composer client:generate`.
- Deleted the Atrium-only tests, fixtures, the without-Atrium suite and its CI job. Added a `client` CI job.
- Rewrote the README, CHANGELOG, Boost skill, `CLAUDE.md` and `AGENTS.md` for v2.0.0.

## Why

Keen v2.0.0 is a JSON API plus MCP and nothing else. Atrium v2 and other front ends read the log through the OpenAPI document and the client. The packages endpoint replaces the History nav that the plugin built in PHP.

Decisions that shaped it:
- D-001: the API stays on by default and `keen.authorization` stays off.
- D-003 and D-004: the list mirrors the old nav rules, using `History::allows()` per package, with no new policy ability.
- D-005: an MCP tool is added for parity.
- D-006 and D-013: translations, publish tags and the without-Atrium suite are removed.
- D-007: the Keystone constraint is unchanged.
- D-008 to D-011: Scramble is configured only in the workbench, a SHA-256 stamp backs the staleness test, and `client/` ships a committed `dist/`.
- D-015: the release jumps from v0.1.0 to v2.0.0.

## Look at

- `src/Domains/Audit/Actions/ListAuditedPackagesAction.php:26`: the filtering and sort rules (skips `keen`, `audits()`, `History::allows`), and that no audit entry is written.
- `workbench/app/Providers/WorkbenchServiceProvider.php:38`: the Scramble configuration and the component schema replacement. Determinism of the export depends on it.
- `tests/Feature/OpenApiTest.php`: the staleness check (a fresh export compared with the committed file, and the stamp) and the shape assertions.
- `composer.json`: Atrium removal, the `client:generate` script, and Scramble in `require-dev` only.
- `config/keen.php`: removal of the `ui` and `atrium` keys; confirm every other default is unchanged.

## Tested

- AC 1 to 3: `tests/Feature/Http/AuditedPackagesTest.php`.
- AC 4: `tests/Feature/AuditedPackagesActionsTest.php`.
- AC 5: `tests/Feature/Mcp/ListAuditedPackagesToolTest.php` and `OutputSchemaTest.php`.
- AC 6: `SurfacesTest`, `PolicyTest` and `Modes/RenamedTables`, unchanged.
- AC 7 to 10: `tests/Feature/RemovedSurfacesTest.php` and `tests/ArchTest.php`.
- AC 11: `tests/Feature/ComposerTest.php`.
- AC 13: `tests/ArchTest.php`.
- AC 14 to 16 and 20: `tests/Feature/OpenApiTest.php`.
- AC 22 to 25: `tests/Feature/DocsTest.php`.
- AC 12, 17 to 19 and 21 are checked by the diff, the committed `client/` files and the CI job.
- Last gate (round 1): PASS. `composer test` ran phpstan with 0 errors, pint, 100% type coverage and 139 Pest tests passing. The ringside check passed.

## Risks

- Breaking v2.0.0 change: routes `atrium.keen.*`, screens, components, config keys, publish tags and `KeenPlugin` are removed with no alias. Consumers on `^0.1` keep the UI until they move.
- Security default unchanged (D-001): the API is on and `keen.authorization` is off, so a fresh install serves the log to anyone who reaches the API middleware. The README says so.
- The TypeScript output is checked authoritatively only by the `client` CI job, not by `composer test`, which has no Node.
- npm does not support subfolder git dependencies, so Atrium must address `client/` itself. This is outside this job.
- Widening the Keystone constraint after Keystone tags its Atrium-hook removal is a release step, left open.
- Findings left: none (nits: 0).
