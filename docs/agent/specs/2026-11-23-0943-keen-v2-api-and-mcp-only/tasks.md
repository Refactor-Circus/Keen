# Tasks: Keen v2: API and MCP only

## Group 1: Audited packages endpoint and MCP tool
depends on: none
files: src/Domains/Audit/Actions/ListAuditedPackagesAction.php, src/Domains/Audit/Events/AuditedPackagesListingActionEvent.php, src/Domains/Audit/Events/AuditedPackagesListedActionEvent.php, src/Domains/Audit/Http/Resources/AuditedPackageResource.php, src/Domains/Audit/Http/Requests/IndexAuditedPackageRequest.php, src/Domains/Audit/Http/Controllers/AuditedPackageController.php, src/Domains/Audit/routes.php, src/Domains/Audit/Mcp/Requests/ListAuditedPackagesMcpRequest.php, src/Domains/Audit/Mcp/Tools/ListAuditedPackagesTool.php, src/Mcp/KeenServer.php, tests/Pest.php, tests/Fixtures/Shop/ShopServiceProvider.php, tests/Feature/AuditedPackagesActionsTest.php, tests/Feature/Http/AuditedPackagesTest.php, tests/Feature/Mcp/ListAuditedPackagesToolTest.php
criteria: 1, 2, 3, 4, 5, 6

- [ ] 1.1 Write tests. `AuditedPackagesActionsTest`: lists audited packages as `Package`s sorted by label then key; never lists `keen`; leaves out a package with `{key}.audit.enabled` false; leaves out a package whose `authorizeHistory()` refuses the viewer and keeps the rest; dispatches the Listing then the Listed event (the Listed event carries the keys); records no audit entry. `Http/AuditedPackagesTest`, by route name `keen.packages.index`: 200 with exact `data` items `{key, label}`; 401 for a guest with `keen.authorization` on. `Mcp/ListAuditedPackagesToolTest`: `mcpTool(ListAuditedPackagesTool::class)` answers the same items and passes the output schema. Add a second fixture package to `ShopServiceProvider` only if one is needed for sort and refusal cases.
- [ ] 1.2 Implement the two `ReadActionEvent` events and `ListAuditedPackagesAction` (`handle(?Authenticatable $viewer = null)`, `PackageRegistry` + `Package::audits()` + `History::allows()`, excludes `keen`, sorts by label then key).
- [ ] 1.3 Implement `AuditedPackageResource` (final, `@mixin Package`, `toArray()` and `schema()` with required `key` and `label`), `IndexAuditedPackageRequest`, `AuditedPackageController::index` and the `packages.index` route in `routes.php`.
- [ ] 1.4 Implement `ListAuditedPackagesMcpRequest` and `ListAuditedPackagesTool` (`outputSchema()` = `AuditedPackageResource::collectionSchema($schema)`), and add the tool to `KeenServer::TOOLS`.
- [ ] 1.5 In `tests/Pest.php` `parityGaps()`, exempt `ListAuditedPackagesAction` from the `ui` surface only (Group 2 removes that surface). Confirm that `SurfacesTest`, `PolicyTest` and `OutputSchemaTest` pass unchanged.
- [ ] 1.6 Run `vendor/bin/pest`; all green.

## Group 2: Remove the Atrium plugin, views, config and Atrium-only tests
depends on: 1
files: src/Atrium/HistoryExtensions.php, src/Atrium/KeenPlugin.php, src/Atrium/Http/Controllers/AuditUiController.php, src/Atrium/Http/Controllers/HistoryUiController.php, src/KeenServiceProvider.php, src/Domains/Audit/Services/KeenAuditTrail.php, config/keen.php, resources/views/components/audit-trail.blade.php, resources/views/components/audit/changes.blade.php, resources/views/components/audit/entries.blade.php, resources/views/ui/activity-tab.blade.php, resources/views/ui/history-section.blade.php, resources/views/ui/history.blade.php, resources/views/ui/index.blade.php, resources/views/ui/show.blade.php, lang/en/keen.php, composer.json, phpunit.without-atrium.xml, .github/workflows/tests.yml, tests/TestCase.php, tests/Pest.php, tests/ArchTest.php, tests/Feature/ComposerTest.php, tests/Feature/RemovedSurfacesTest.php, tests/Feature/StylesTest.php, tests/Feature/Ui/*, tests/WithoutAtrium/*, tests/Fixtures/Screens/*
criteria: 7, 8, 9, 10, 11, 12

- [ ] 2.1 Write tests. New `RemovedSurfacesTest`: no file in `src/` or `config/` contains `Atrium`; no `keen` view or translation namespace; `publishableGroups()` lacks `keen-views` and `keen-lang`; the `keen.*` route names are exactly the four API routes (MCP route only when `keen.mcp.web.enabled`), and no `atrium.keen.*` route exists; `config('keen.ui')` and `config('keen.atrium')` are null. `ArchTest`: "nothing uses Atrium" (`not->toUse('RefactorCircus\Atrium')`, no `ignoring`); rename the parity rule to "HTTP API and MCP". `ComposerTest`: no `refactor-circus/atrium` in require, require-dev, suggest or conflict; no Atrium repository; no `extra.atrium`; no `test:without-atrium` script; keystone the only suite require; description mentions the JSON API and MCP and not Atrium. Remove the `without-atrium` CI assertions.
- [ ] 2.2 Delete `src/Atrium/`, `resources/views/` and `lang/`. In `KeenServiceProvider`, drop `registerAtriumPlugin()`, `registerHistoryExtensions()`, `loadViewsFrom()`, `loadTranslationsFrom()` and the `keen-views`/`keen-lang` publishes. Reword the `KeenAuditTrail` docblock so it no longer mentions Atrium.
- [ ] 2.3 In `config/keen.php`, delete the `ui` and `atrium` sections and remove the dashboard mention from the Authorization banner.
- [ ] 2.4 In `composer.json`, remove `refactor-circus/atrium` from require-dev, suggest and conflict, remove the Atrium VCS repository and the `test:without-atrium` script and its entry in `test`, and update the description. Run `composer update` in the worktree.
- [ ] 2.5 Delete `tests/Feature/Ui/`, `tests/Feature/StylesTest.php`, `tests/WithoutAtrium/`, `tests/Fixtures/Screens/`, `phpunit.without-atrium.xml` and the `without-atrium` job in `.github/workflows/tests.yml`. Remove `AtriumServiceProvider` and Atrium's migrations from `TestCase`. In `tests/Pest.php`, remove `ResetsScreenExtensions`, drop the `ui` surface from `parityGaps()` and drop Group 1's exemption.
- [ ] 2.6 Run `composer analyse` and `vendor/bin/pest`; all green.

## Group 3: OpenAPI export with Scramble
depends on: 2
files: composer.json, workbench/app/Providers/WorkbenchServiceProvider.php, testbench.yaml, src/Domains/Audit/Http/Controllers/AuditController.php, src/Domains/Audit/Http/Controllers/AuditedPackageController.php, openapi.json, tests/ArchTest.php, tests/Feature/OpenApiTest.php
criteria: 13, 14, 15, 16

- [ ] 3.1 Write tests. `OpenApiTest`: registers Scramble's provider and `WorkbenchServiceProvider`; runs `scramble:export` to a temp path; the result equals the committed `openapi.json` as decoded JSON (the failure message names `composer client:generate`); two exports are byte-identical; OpenAPI 3.1 with `info.version` `2.0.0`; `paths` exactly `/entries` (get, post), `/entries/{entry}` (get) and `/packages` (get), with a server ending in `/api/keen`; 2xx JSON responses reach the `AuditEntryResource` and `AuditedPackageResource` components (`key`, `label`); `GET /entries` has the `source`, `action`, `cursor` and `per_page` query parameters; `POST /entries` has a body with `action`. `ArchTest`: `RefactorCircus\Keen` does not use `Dedoc\Scramble`.
- [ ] 3.2 Run `composer require --dev dedoc/scramble` (a release supporting PHP 8.5 and Laravel 13; stop and report if there is none).
- [ ] 3.3 Configure Scramble in `WorkbenchServiceProvider` (`api_path` from `keen.routes.prefix`, fixed `app.url`, info version `2.0.0`, title and description) and enable the provider in `testbench.yaml`.
- [ ] 3.4 Add `@response` PHPDoc types to the `AuditController` and `AuditedPackageController` methods. Add a workbench document transformer only if the components still come out wrong.
- [ ] 3.5 Export with `vendor/bin/testbench scramble:export --path=openapi.json` and commit `openapi.json`.
- [ ] 3.6 Run `vendor/bin/pest`; all green.

## Group 4: Generated TypeScript client and staleness check
depends on: 3
files: client/package.json, client/package-lock.json, client/tsconfig.json, client/src/index.ts, client/src/schema.d.ts, client/dist/index.js, client/dist/index.d.ts, client/openapi.sha256, bin/client-stamp.php, composer.json, .gitignore, .gitattributes, .github/workflows/tests.yml, tests/Feature/OpenApiTest.php, tests/Feature/ComposerTest.php
criteria: 17, 18, 19, 20, 21

- [ ] 4.1 Write tests. `OpenApiTest`: `client/openapi.sha256` equals the SHA-256 of the LF-normalized `openapi.json` (the message names `composer client:generate`); `client/src/schema.d.ts`, `client/dist/index.js` and `client/dist/index.d.ts` exist; `client/package.json` has name `@refactor-circus/keen-client`, version `2.0.0`, `type` module, exports and types into `dist/`, `openapi-fetch` as its only dependency, and exact-pinned `openapi-typescript` and `typescript`; `client/src/index.ts` exports `createKeenClient`, `KeenClient`, `AuditEntry` and `AuditedPackage`. `ComposerTest`: the `client:generate` script runs, in order, the scramble export, `npm --prefix client ci`, `npm --prefix client run generate` and `@php bin/client-stamp.php`, and `test` does not include it; the tests workflow has a `client` job running `composer client:generate` then `git diff --exit-code -- openapi.json client`; `.gitattributes` does not export-ignore `client` or `openapi.json`.
- [ ] 4.2 Create `client/package.json` (`generate`: `openapi-typescript ../openapi.json -o src/schema.d.ts && tsc -p tsconfig.json`), `tsconfig.json` and `src/index.ts`: `createKeenClient(options: ClientOptions & { baseUrl: string })`, plus re-exports of `paths` and `components` and the `AuditEntry` and `AuditedPackage` aliases. Add `/client/node_modules` to `.gitignore`.
- [ ] 4.3 Add `bin/client-stamp.php` and the `client:generate` composer script. Run it, and commit `package-lock.json`, `src/schema.d.ts`, `dist/` and `openapi.sha256`.
- [ ] 4.4 Add the `client` job to `.github/workflows/tests.yml` (PHP 8.5, Node LTS, `composer install`, `composer client:generate`, `git diff --exit-code -- openapi.json client`).
- [ ] 4.5 Run `vendor/bin/pest`; all green.

## Group 5: Docs, CHANGELOG and Boost skill
depends on: 4
files: README.md, CHANGELOG.md, resources/boost/skills/keen-development/SKILL.md, CLAUDE.md, AGENTS.md, tests/Feature/DocsTest.php
criteria: 22, 23, 24, 25, 26

- [ ] 5.1 Write tests. `DocsTest`: the README, Boost skill, `CLAUDE.md` and `AGENTS.md` contain none of `keen.ui.enabled`, `keen.atrium`, `x-keen::`, `atrium.keen.history.show`, `registerAtriumPlugin` or `test:without-atrium`. The README mentions `keen.packages.index`, `openapi.json`, `@refactor-circus/keen-client` and `client:generate`. `CHANGELOG.md` has `### Upgrading to v2.0.0` under `## [Unreleased]`, naming Atrium v2 and `atrium.integrations.keen`.
- [ ] 5.2 README: describe Keen as API + MCP and remove "History in Atrium". Document `GET {prefix}/packages` and the MCP tool, and add "OpenAPI and TypeScript client" (git-dependency install on the tag, a `createKeenClient({ baseUrl })` example, `composer client:generate`). Name Atrium v2 as the optional UI and keep the security note.
- [ ] 5.3 CHANGELOG `[Unreleased]`: `### Breaking` (every removed item from the spec's Public API change), `### Added` and `### Upgrading to v2.0.0`.
- [ ] 5.4 Boost skill, `CLAUDE.md` and `AGENTS.md`: remove the Atrium and without-Atrium bullets, and add the packages endpoint, the OpenAPI/client workflow and the staleness test. Leave the Ring Master block alone.
- [ ] 5.5 Run `composer test`; all green.
