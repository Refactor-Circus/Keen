# Keen v2: API and MCP only — Shaping Notes

## Scope

Keen keeps its JSON API (`keen.entries.*`), Keystone's per-package `GET {prefix}/history` routes and MCP, and drops its Atrium plugin (audit log pages, per-package History nav, the history section on every screen, the activity tab on `roster.organization`).

This package's part of the suite move to a headless Atrium v2. It changes this package only.

- New API, missing today: `GET keen/packages`, the audited packages with their labels, so Atrium can build the per-package History nav.
- The `components/audit*` Blade views go with the rest of the Blade UI.
- Run Scramble against the workbench and commit `openapi.json`; generate and commit `client/` (`openapi-typescript` + `openapi-fetch`); add a composer script (`client:generate`) and a staleness test.
- Delete `src/Atrium/**`, Atrium Blade views, Atrium-only tests and Atrium config keys; remove the Atrium composer entries and the provider's Atrium registration.
- README, CHANGELOG (v2.0.0 upgrade note), Boost skill and `CLAUDE.md` describe the package as API + MCP, with Atrium v2 as the optional UI.

## Decisions

- **Atrium hosts this package's front end.** Atrium's v2 spec (`Atrium/docs/agent/specs/2026-11-22-1823-atrium-v2-headless`) ports this package's pages, widgets, search and settings into Atrium. Atrium registers them only when this package is installed (`Composer\InstalledVersions` plus `atrium.integrations.keen.enabled`). Atrium's Vue calls this package's JSON API through this package's generated client. Decided with the user, 2026-11-23.
- **This package keeps only its JSON API and MCP.** It deletes `src/Atrium`, its Blade views and its Atrium-only tests. It drops `refactor-circus/atrium` from `require-dev`, `suggest` and `conflict`, and drops `extra.atrium`. Nothing in `src/` names Atrium.
- **No knowledge of Atrium.** The suite rule still holds for this package: Keystone is the only suite `require`, and the code never names or detects another suite package. Atrium is the one package allowed to know the others.
- **API client.** OpenAPI comes from `dedoc/scramble` (`require-dev`), exported against the workbench into a committed `openapi.json`. The TypeScript client is generated with `openapi-typescript` plus `openapi-fetch` into a committed `client/` folder, with its own `package.json` (`@refactor-circus/keen-client`). A test or CI job fails when either is stale. Atrium v2 is a prebuilt Vue app served by a Blade page; Keen ships its own Atrium module in `dist/atrium`, typed by this client.
- **Missing data becomes API.** Anything the Blade UI computed in PHP that has no API route becomes a new endpoint here: widget aggregates (`GET {prefix}/stats`), search (`GET {prefix}/search?q=`), and a settings summary (`GET {prefix}/settings-summary`) where the package had a settings panel. These follow the package's existing Request/Action/Resource pattern and its policies.
- **Atrium-only config goes.** `keen.ui.enabled`, `keen.atrium.*` and the `KeenSupportFeature` move to Atrium's `atrium.integrations.keen.*`, owned by the Atrium spec.
- **Release v2.0.0.** This is a breaking change (Atrium UI removed). The CHANGELOG has an upgrade note pointing to Atrium v2.
- Config: `keen.atrium.history.*` (screens, slot, exclude, tabs, nav) moves to Atrium. `ImportRosterAuditCommand` and `keen.cortex` name other suite packages; they are flagged here and out of scope.

## Context

- **Inventory** (2026-11-23): the plugin, pages, widgets, search, settings and extensions are listed in `references.md`.
- **Order:** this spec waits for Keystone `2026-11-23-0943-drop-the-atrium-plugin-hook`, which removes `registerAtriumPlugin()` and the `ui.enabled` convention. Atrium's v2 integration groups wait for this package's v2.0.0 tag, because Keen's Atrium module is built against it.
- **Product docs:** none to align with.
- **Open for write-spec:** whether the package's API stays off by default (`<pkg>.routes.enabled`); the exact stats payload; and whether any package-to-package references flagged above become their own specs.

## Standards

- `http/routes`: new endpoints in the package's route group, named `keen.*`.
- `http/resources`: every new payload is a final resource.
- `http/request-persist`: new endpoints go through Requests and Actions.
- `http/authorization`: stats, search and settings summary authorize through policies.
- `architecture/dependencies`: no Atrium reference left; Keystone is the only suite require.
- `architecture/configuration`: removed Atrium keys; banner notes for the client.
- `testing/test-layers`: API wiring tests; behavior stays at the actions.
