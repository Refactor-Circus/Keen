# Decisions: Keen v2: API and MCP only

## D-001: Does the JSON API stay on by default (`keen.routes.enabled`)?
- Choice: Yes. `keen.routes.enabled` stays `true`, with `prefix` `api/keen` and `middleware` `['api']` unchanged from v0.1.
- Rationale: This keeps the v0.1 behavior. Atrium v2 reads Keen only through this API, so turning it off would add a second breaking change to the upgrade with nothing gained. The security default (`keen.authorization` false) is also unchanged; it is listed under Risks rather than changed here.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-002: Which "missing data" endpoints does Keen need: stats, search, settings summary?
- Choice: Only `GET {prefix}/packages`. Keen adds no `stats`, `search` or `settings-summary` endpoint.
- Rationale: `KeenPlugin` registers no widgets, no search source and no settings panel; it has only pages and nav items. The pages' data is already served by `keen.entries.index` (its filters cover search by action, prefix, subject, actor, scope and time), `keen.entries.show` and each package's `GET {prefix}/history`. The per-package nav list is the only data computed in PHP with no route (`KeenPlugin::historyItems()`).
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-003: What does `GET {prefix}/packages` return, and which packages does it list?
- Choice: `{"data": [{"key": "...", "label": "..."}]}`. The list holds every package in Keystone's `PackageRegistry` whose `audits()` is true and whose history rule (`History::allows($package, $user, [])`) lets the caller read it, except `keen` itself. It is sorted by label, then key, and is not paginated. The application's own `app` source is not listed. Route `keen.packages.index`, URI `packages`. The classes are `AuditedPackageController::index`, `IndexAuditedPackageRequest`, `ListAuditedPackagesAction` and `AuditedPackageResource`.
- Rationale: This mirrors exactly what `KeenPlugin::historyItems()` showed, so Atrium can rebuild the nav without new rules. Keen's own actions are never recorded, so its history is always empty, which is why `keen` was left out before. The registry is small (one entry per installed package), so paging would only add client work. Only key and label are sent: the nav needs nothing else, and the per-package API prefix comes from Atrium's shell.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-004: Who may call `GET {prefix}/packages`?
- Choice: Keystone's base `Request::authorize()` (a signed-in user while `keen.authorization` is on: guests get 401), then per-package filtering with `History::allows()`. No new policy ability.
- Rationale: The list is a projection of each package's own history rule, the rule the old nav items used. Adding a Keen ability on top would let the two drift apart.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-005: Does the packages list get an MCP tool too?
- Choice: Yes. `ListAuditedPackagesTool` (`list-audited-packages-tool`) on `KeenServer`, backed by `ListAuditedPackagesMcpRequest` and the same action and resource.
- Rationale: The package's parity rule (`tests/ArchTest.php`, Keystone `Parity::gaps()`) requires every action to be reachable from the JSON API and MCP. The tool is small and keeps the "API + MCP" promise.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-006: What happens to `lang/` and the `keen-views` / `keen-lang` publish tags?
- Choice: Removed. `lang/en/keen.php` is deleted, and the provider no longer calls `loadViewsFrom()` or `loadTranslationsFrom()` or publishes `keen-views` or `keen-lang`.
- Rationale: Only the Atrium UI and the Blade views read these strings, and nothing in the remaining `src/` uses them. Atrium v2 ports its own strings. Leaving dead translations and publish tags would advertise a UI that is gone.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-007: Does the Keystone constraint change?
- Choice: No. `refactor-circus/keystone` stays `^0.1` in `require`. Widening it after Keystone tags `2026-11-23-0943-drop-the-atrium-plugin-hook` is a release step, not part of this spec.
- Rationale: Once `registerAtriumPlugin()` is no longer called, Keen runs on Keystone both before and after that change. This spec waits for the Keystone job, so the build already runs against Keystone without the hook.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-008: Where does the Scramble configuration live, and what does the document describe?
- Choice: In `Workbench\App\Providers\WorkbenchServiceProvider`, which `testbench.yaml` enables, and which `tests/Feature/OpenApiTest.php` registers as well. It sets `scramble.api_path` to `keen.routes.prefix` (`api/keen`), so paths are relative (`/entries`, `/entries/{entry}`, `/packages`). It also sets a fixed `app.url` (`http://localhost`), `info.version` `2.0.0` and a title and description for Keen. `dedoc/scramble` is `require-dev` only, at the newest release that supports PHP 8.5 and Laravel 13. Response schemas come from `@response` PHPDoc types on the controller methods, because `$request->persist()` hides the resource from inference.
- Rationale: One place configures both the composer script and the test, so they cannot diverge. Relative paths keep the document correct for any host prefix. `src/` stays free of Scramble.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-009: How does a PHP test catch a stale TypeScript client without Node?
- Choice: `composer client:generate` writes `client/openapi.sha256`: the SHA-256 of `openapi.json` with line endings normalized to LF. `tests/Feature/OpenApiTest.php` fails when that stamp does not match the committed `openapi.json`, and also when a fresh Scramble export differs from the committed `openapi.json` (compared as decoded JSON). A new `client` CI job installs Node, runs `composer client:generate` and fails on `git diff --exit-code -- openapi.json client`. That job is the authoritative check of the generated TypeScript.
- Rationale: `composer test` runs on Windows and without Node. The stamp catches the usual mistake (the API changed and the client was not regenerated). The CI job catches drift in the generator output itself.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-010: What does `client/` ship, and how are its tools pinned?
- Choice: `client/package.json` names `@refactor-circus/keen-client` at version `2.0.0`, `"type": "module"`, with `exports` pointing at a committed `dist/` (`index.js`, `index.d.ts`, built by `tsc`). The TypeScript sources (`src/index.ts`, the generated `src/schema.d.ts`) are committed too. `openapi-fetch` is the one runtime `dependency`, with a caret range, which on 0.x locks the minor. `openapi-typescript` and `typescript` are exact-pinned `devDependencies`. `package-lock.json` is committed and `client/node_modules` is ignored. Neither `client/` nor `openapi.json` is `export-ignore`d.
- Rationale: consumers pin the client as a git dependency on the v2 tag (Keen's own Atrium module imports its types directly). A committed `dist/` means consumers need no build step, and git tarballs honor `export-ignore`, so the folder must not be ignored. Exact generator pins keep regeneration byte-stable.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-011: What is the typed wrapper's API?
- Choice: `createKeenClient(options)` returns `createClient<paths>(options)` from `openapi-fetch`. `options` is `openapi-fetch`'s `ClientOptions` with `baseUrl` required, for example `https://app.test/api/keen`. The package also exports `KeenClient` (its return type), `paths`, `components`, and the aliases `AuditEntry` and `AuditedPackage` for the resource schemas.
- Rationale: The host chooses the prefix (`keen.routes.prefix`), so the client must not assume one. Exporting the schema aliases spares consumers from indexing `components`.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-012: Where does the v2.0.0 upgrade note go in the CHANGELOG?
- Choice: Under `## [Unreleased]`, as `### Breaking`, `### Added` and `### Upgrading to v2.0.0` subsections. The v2.0.0 release notes reuse them, and the `update-changelog` workflow adds the versioned section when the release is published.
- Rationale: The tag does not exist yet, and the changelog workflow owns the versioned headings.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-013: What happens to the without-Atrium suite?
- Choice: It is deleted: `tests/WithoutAtrium/`, `phpunit.without-atrium.xml`, the `test:without-atrium` composer script and its place in `composer test`, and the `without-atrium` CI job. The proof becomes the arch rule (nothing uses `RefactorCircus\Atrium`), the source grep and the composer manifest test.
- Rationale: With Atrium no longer installed even in dev, every suite already runs without it.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-014: Do the flagged package-to-package references become their own specs?
- Choice: No, not from this spec. `ImportRosterAuditCommand` (`keen:import-roster`), its README section "Moving from Roster's audit log", the `keen.cortex` config and `registerCortex()`, the `showroom`/`pennantplus` names in the removed nav groups, and the recorded surface value `atrium` (in `KeenServer`'s instructions and test data) are all untouched. Each one that needs work gets its own shaped spec later.
- Rationale: The shape flagged these as out of scope. The surface value is recorded data, not a code dependency on Atrium.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-015: Release number and spec review
- Choice: Release `v2.0.0`, as `meta.yml` says (it follows `v0.1.0`). The client's package version is `2.0.0` as well. `/ring-master-spec-review` was skipped on the user's instruction, so `meta.yml` goes straight to `status: approved`.
- Rationale: The suite-wide Atrium v2 plan pins every package client to its v2.0.0 tag. The user asked to proceed without review or questions.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-016: Which version does the suite release under?
- Choice: Every suite package releases as v2.0.0 to match: Keystone, Atrium, Cortex, Impex, Keen, PennantPlus, Polycart, Roster and Showroom. Every `refactor-circus/*` constraint (require and require-dev) is `^2.0`, and every repo aliases `dev-main` to `2.0.x-dev`. Foundry and Guardian stay on Keystone `^0.1` until they migrate.
- Rationale: The user said "i'd like everything release as v2 to match". This supersedes any earlier choice to keep `^0.1` constraints or release Keystone as v0.2.0.
- Date: 2026-11-23
- Raised by: user

## D-017: Who owns Keen's Atrium front end?
- Choice: Keen does. Its Atrium v2 module (pages, History section, Activity tab, History nav resolver), staged in Atrium's `ui/modules/keen` during Atrium's phase 1, moves into `atrium/` here, typed by Keen's own `client/src/schema.d.ts` (the vendored copy is dropped) and built with the SDK `@refactor-circus/atrium` pinned to Atrium commit `ad559e4c` as a dev dependency. The build `dist/atrium/` is committed and declared in `composer.json` `extra.atrium`; Atrium injects the script into its Blade page. Keen still has no Atrium PHP and no Composer dependency on Atrium: the "no Atrium" tests are narrowed to allow `extra.atrium`, `atrium/`, `dist/atrium/`, the `ui` CI job and the docs, and still forbid Atrium in `src/`, `config/`, the routes and the workbench PHP. This revises the "nothing else" of the spec's JSON-API-only scope.
- Rationale: The user said "move the vue files for each module back into their respective modules. modules need to register their js. js should be injected into the blade file. modules are responsible for their own frontend and cjs clients from now on".
- Date: 2026-11-24
- Raised by: user

## D-018: Where is Keen's Atrium integration declared?
- Choice: In Keen, as the static JSON manifest `atrium/src/integration.json` (Atrium commit `009b5bc5`, MODULES.md §8): the `keen.atrium` feature, the Audit log and live History nav items, the screens `keen.entries`, `keen.entry` and `keen.history`, and the two hooks Keen owns, the `keen.history` section (`targets`, config `history`) and the `keen.activity` tab (`options`, config `history.tabs`). The SDK preset validates it on `pnpm build` and writes `dist/atrium/integration.json`, declared in `composer.json` `extra.atrium.integration`. The SDK pin moves to `009b5bc5`. `$config` expressions read Atrium's `atrium.integrations.keen.*` keys with the manifest's defaults (sort 90, groups showroom/pennantplus); Keen adds no config and no Atrium PHP. A non-array `history.nav` now falls back to the manifest defaults.
- Rationale: The user asked to "move the php declarations": each package owns its Atrium integration declaration, and Atrium (PR #20) reads it as JSON, so Keen still needs no Atrium dependency.
- Date: 2026-11-24
- Raised by: user
