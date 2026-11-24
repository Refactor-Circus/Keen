<p align="center">
    <img src="art/icon.png" width="160" alt="Keen icon">
</p>

# Keen

A tamper-evident audit log for every package of the Refactor Circus suite, and for your application's own events, served as a JSON API and MCP tools.

Install Keen and every action of every suite package (Atrium, Cortex, Impex, PennantPlus, Polycart, Roster, Showroom) is recorded: who did it, to which record, through which surface (`atrium`, `http`, `mcp`, `cortex`, `cli`, `code`), and which fields changed. No package depends on Keen; each announces its actions through the shared events of [refactor-circus/keystone](https://github.com/Refactor-Circus/Keystone), and Keen listens.

- **Append-only and hash-chained.** Each entry's hash covers the one before it, so editing or removing any entry breaks the chain. `php artisan keen:verify` finds the first altered entry.
- **Secrets never reach the log.** Passwords, tokens, client secrets and private keys are redacted, as is anything a package or your config names; a redacted field still shows that it changed.
- **A JSON API and MCP.** Read and record entries over the JSON API or the MCP server (and so from Cortex agents). Keen ships no Blade screens: a front end reads the log through the committed OpenAPI document and the generated TypeScript client `@refactor-circus/keen-client`.
- **Every package keeps its own history.** Once Keen is installed, each package's `GET {prefix}/history` endpoint and `list-{package}-history-tool` MCP tool show its own entries, and `GET {prefix}/packages` lists the packages whose history the caller may read. Packages declare nothing for this.
- **Atrium v2 is the optional UI.** Keen ships its own [Atrium](https://github.com/Refactor-Circus/Atrium) v2 front-end module: the audit log pages, every package's History page, section and Activity tab, read from this API. It is JavaScript only, prebuilt in `dist/atrium/` and declared in `composer.json` under `extra.atrium`; Atrium v2 finds it and loads it once the keen integration is active (configured under Atrium's `atrium.integrations.keen` keys). Keen does not require Atrium, and has no Atrium PHP.

## Installation

```bash
composer require refactor-circus/keen
php artisan migrate
```

Publish the config to change it:

```bash
php artisan vendor:publish --tag=keen-config
```

The log lives in `keen_entries`, with the head of its hash chain in `keen_chain`. To use other names, set them under `keen.tables` (`entries`, `chain`) before running the migrations; a published config that lists only some keeps the defaults for the rest:

```php
'tables' => [
    'entries' => 'audit_entries',
    'chain' => 'audit_chain',
],
```

Schedule pruning if you keep a retention period (365 days by default):

```php
Schedule::command('keen:prune')->daily();
```

## Recording your own events

Package actions are recorded on their own. Record your application's events with the facade; they are stored with source `app`, so they can never pass for an entry a package made:

```php
use RefactorCircus\Keen\Facades\Keen;

Keen::record('invoice.paid')
    ->on($invoice)            // the record it happened to
    ->in($organization)       // optional scope, for reading the log per tenant
    ->by($user)               // defaults to the signed-in user
    ->with(['amount' => 100]) // context
    ->changes(['status' => ['open', 'paid']])
    ->save();
```

Recorded inside a database transaction, the entry is appended to the chain once the transaction commits, so the chain is never locked for the rest of your transaction; until then `save()` returns the entry unsaved (`exists` is false, no id or hash yet) and fills it in on commit. If the transaction rolls back, the entry is never written. Package actions' entries already come after the commit.

A package of the suite recording one of its own events adds `->source('polycart')` (a registered package key), so the entry shows in that package's history. The JSON API and MCP always record as `app`.

## Reading the log

| Surface | Read | Record |
|---|---|---|
| JSON API (`keen.routes`) | `GET api/keen/entries`, `GET api/keen/entries/{id}`, `GET api/keen/packages` | `POST api/keen/entries` |
| MCP (`keen.mcp`) | `list-audit-entries-tool`, `show-audit-entry-tool`, `list-audited-packages-tool` | `record-audit-event-tool` |

The JSON API's routes are `keen.entries.index`, `keen.entries.show`, `keen.entries.store` and `keen.packages.index`, under `keen.routes.prefix` (`api/keen` by default) with the `keen.routes.middleware` (`['api']`).

Filters: `source` (a package key or `app`), `action` (exact, or a prefix ending in `.`), `subject_type` + `subject_id`, `actor_type` + `actor_id`, `scope_type` + `scope_id`, `since`, `until`. Listings are cursor paginated: pass `per_page` (up to 100) and the previous page's next cursor (`meta.next_cursor`) as `cursor`.

## Audited packages: `GET {prefix}/packages`

`GET {prefix}/packages` (route `keen.packages.index`, default `GET /api/keen/packages`) lists the packages whose history the caller may read, so a front end can build a per-package History nav without knowing the suite:

```json
{
    "data": [
        {"key": "polycart", "label": "Polycart"},
        {"key": "roster", "label": "Roster"}
    ]
}
```

A package is listed while it is registered with refactor-circus/keystone, its auditing is on (`{key}.audit.enabled`) and its history rule lets the caller read it. Keen itself and the application's `app` source are never listed. The list is sorted by label, then key, and is not paginated. With `keen.authorization` on, a guest gets 401.

The MCP tool `list-audited-packages-tool` answers the same items as structured content. Each package's own entries come from its `GET {package prefix}/history` endpoint and `list-{package}-history-tool` tool, or from `GET {prefix}/entries?source={key}`.

## OpenAPI and TypeScript client

`openapi.json`, at the root of the repository, describes the JSON API as an OpenAPI 3.1 document (`info.version` `2.0.0`). Its paths (`/entries`, `/entries/{entry}`, `/packages`) are relative to a server ending in `/api/keen`, so point your tools at your own `keen.routes.prefix` URL.

`client/` holds `@refactor-circus/keen-client`, a typed [openapi-fetch](https://openapi-ts.dev/openapi-fetch/) client generated from that document. It is not published to a registry: install it as a git dependency on the release tag. npm can't install a subfolder of a git repository, so use pnpm with its `path:` option:

```bash
pnpm add "@refactor-circus/keen-client@github:Refactor-Circus/Keen#v2.0.0&path:/client"
```

The package ships its built `dist/`, so it needs no build step. Create a client with the URL of your `keen.routes.prefix`; it assumes no prefix of its own:

```ts
import { createKeenClient, type AuditEntry, type AuditedPackage } from '@refactor-circus/keen-client';

const keen = createKeenClient({ baseUrl: 'https://app.test/api/keen' });

const { data: packages } = await keen.GET('/packages');
const { data: entries } = await keen.GET('/entries', {
    params: { query: { source: 'roster', per_page: 25 } },
});
```

It also exports `KeenClient`, `paths`, `components`, `AuditEntry` and `AuditedPackage`.

When the API changes, regenerate the document and the client together (this needs Node and npm):

```bash
composer client:generate
```

It exports `openapi.json` with Scramble against the Testbench workbench, runs `npm --prefix client ci` and `npm --prefix client run generate` (`openapi-typescript` and `tsc`), and writes `client/openapi.sha256`. Commit all of it. `composer test` fails, naming `composer client:generate`, when a fresh export differs from `openapi.json` or the stamp does not match it, and the `client` CI job fails when the regenerated files differ from the committed ones.

## Authorization

**Security note.** By default `keen.routes.enabled` is on and `keen.authorization` is off, so a fresh install serves the whole log, and records events, for anyone who reaches the `api` middleware. Turn `keen.authorization` on, put the routes behind your own authentication middleware (`keen.routes.middleware`), or turn the routes off before you deploy.

With `keen.authorization` on, every surface acts as the signed-in user and asks the policy in `keen.policies`. The bundled policy defers to Gate abilities when your application defines them:

```php
Gate::define('viewAuditLog', fn (User $user, ?string $source) => $user->isAuditor());
Gate::define('recordAuditEvent', fn (User $user) => $user->isAdmin());
```

Without them anyone signed in may read and record. People may always read the entries they made or that are about them, and IP addresses and user agents are shown only to those who may read the whole log. The same `viewAuditLog` ability guards every package's history endpoint and tool.

To change the rules outright, point the model at a policy of your own in `keen.policies` (extending the bundled one, or not):

```php
'policies' => [
    AuditEntryModel::class => App\Policies\AuditPolicy::class,
],
```

## How packages shape their entries

Packages teach Keen about their models through refactor-circus/keystone's `AuditHooks`, from their service provider, whether or not Keen is installed:

```php
use RefactorCircus\Keystone\Audit\AuditHooks;

app(AuditHooks::class)
    ->label(RoleModel::class, fn (RoleModel $role) => $role->name)
    ->snapshot(RoleModel::class, fn (RoleModel $role) => ['permissions' => $role->permissions->pluck('name')->all()])
    ->context(fn (object $event, ?Model $subject) => ['impersonator' => ...])
    ->scope(fn (object $event, array $models, ?Model $subject) => $models['organization'] ?? null)
    ->redact('client_secret');
```

An action event can also name its subject and context itself by implementing `RefactorCircus\Keystone\Audit\Contracts\Auditable`.

## Moving from Roster's audit log

Roster kept its own audit log before Keen. Copy it across once, in order, into Keen's chain:

```bash
php artisan keen:import-roster
```

Entries are appended in chunks of 500 (`AuditLog::appendMany()`: one chain lock and one bulk insert per chunk), so their model events do not fire.

## Commands

| Command | Does |
|---|---|
| `keen:verify` | Checks the hash chain and reports the first altered entry |
| `keen:prune {--days=}` | Deletes entries older than `keen.retention_days` |
| `keen:import-roster` | Copies Roster's pre-Keen audit log into Keen |

## Testing

```bash
composer test
```

`composer test` runs static analysis, the formatting check, type coverage and the Pest suite. It needs no Node: its OpenAPI test compares a fresh Scramble export with the committed `openapi.json`, and `client/openapi.sha256` with that file. Run `composer client:generate` when it asks you to.

## The Atrium module

The module's Vue sources live in `atrium/` (not part of the Composer archive); the built `dist/atrium/` is committed, so hosts never run Node. After changing it, rebuild with pnpm and commit both:

```bash
cd atrium
pnpm install
pnpm typecheck && pnpm test && pnpm build
```

`composer test` fails when `dist/atrium/build.json` no longer matches the sources, and the `ui` CI job rebuilds and fails when the result differs from the committed build.

What Keen adds to Atrium is declared in a static JSON manifest, `atrium/src/integration.json` (no PHP): its feature flag, the "Audit log" and live "History" nav items, the screens `keen.entries`, `keen.entry` and `keen.history`, the `keen.history` History section on every package's screens (config `history`) and the `keen.activity` Activity tab on detail screens (config `history.tabs`). Its `$config` values read Atrium's `atrium.integrations.keen.*` keys, with the manifest's defaults. To change it, edit `atrium/src/integration.json` and rebuild: `pnpm build` validates it against the SDK's schema and writes `dist/atrium/integration.json`, which `composer.json` declares as `extra.atrium.integration`. `pnpm test` checks that every screen, hook section and tab, and live resolver it declares is registered by the module.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
