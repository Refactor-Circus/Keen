# Keen

A tamper-evident audit log for every package of the Refactor Circus suite, and for your application's own events.

Install Keen and every action of every suite package (Atrium, Cortex, Impex, Keystone, PennantPlus, Polycart, Roster) is recorded: who did it, to which record, through which surface (`atrium`, `http`, `mcp`, `cortex`, `cli`, `code`), and which fields changed. No package depends on Keen; each announces its actions through the shared events of [refactor-circus/foundation](https://github.com/Refactor-Circus/Foundation), and Keen listens.

- **Append-only and hash-chained.** Each entry's hash covers the one before it, so editing or removing any entry breaks the chain. `php artisan keen:verify` finds the first altered entry.
- **Secrets never reach the log.** Passwords, tokens, client secrets and private keys are redacted, as is anything a package or your config names; a redacted field still shows that it changed.
- **API, MCP and Atrium parity.** Read and record entries over the JSON API, the MCP server (and so from Cortex agents), or the Atrium dashboard.
- **Every package keeps its own history.** Once Keen is installed, each package's `GET {prefix}/history` endpoint, `list-{package}-history-tool` MCP tool and Atrium screens show its own entries.

## Installation

```bash
composer require refactor-circus/keen
php artisan migrate
```

Publish the config to change it:

```bash
php artisan vendor:publish --tag=keen-config
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

A package of the suite recording one of its own events adds `->source('polycart')` (a registered package key), so the entry shows in that package's history. The JSON API and MCP always record as `app`.

## Reading the log

| Surface | Read | Record |
|---|---|---|
| JSON API (`keen.routes`) | `GET api/keen/entries`, `GET api/keen/entries/{id}` | `POST api/keen/entries` |
| MCP (`keen.mcp`) | `list-audit-entries-tool`, `show-audit-entry-tool` | `record-audit-event-tool` |
| Atrium | **Audit log** in the sidebar | the **Record an event** form |

Filters: `source` (a package key or `app`), `action` (exact, or a prefix ending in `.`), `subject_type` + `subject_id`, `actor_type` + `actor_id`, `scope_type` + `scope_id`, `since`, `until`. Listings are cursor paginated.

Any package screen can show its own history with Atrium's component, which renders nothing until Keen is installed:

```blade
<x-atrium::audit-trail source="keystone" :subject="$product" />
```

## Authorization

With `keen.authorization` on, every surface acts as the signed-in user and asks the policy in `keen.policies`. The bundled policy defers to Gate abilities when your application defines them:

```php
Gate::define('viewAuditLog', fn (User $user, ?string $source) => $user->isAuditor());
Gate::define('recordAuditEvent', fn (User $user) => $user->isAdmin());
```

Without them anyone signed in may read and record. People may always read the entries they made or that are about them, and IP addresses and user agents are shown only to those who may read the whole log. The same `viewAuditLog` ability guards every package's history endpoint and tool.

## How packages shape their entries

Packages teach Keen about their models through refactor-circus/foundation's `AuditHooks`, from their service provider, whether or not Keen is installed:

```php
use RefactorCircus\Foundation\Audit\AuditHooks;

app(AuditHooks::class)
    ->label(RoleModel::class, fn (RoleModel $role) => $role->name)
    ->snapshot(RoleModel::class, fn (RoleModel $role) => ['permissions' => $role->permissions->pluck('name')->all()])
    ->context(fn (object $event, ?Model $subject) => ['impersonator' => ...])
    ->scope(fn (object $event, array $models, ?Model $subject) => $models['organization'] ?? null)
    ->redact('client_secret');
```

An action event can also name its subject and context itself by implementing `RefactorCircus\Foundation\Audit\Contracts\Auditable`.

## Moving from Roster's audit log

Roster kept its own audit log before Keen. Copy it across once, in order, into Keen's chain:

```bash
php artisan keen:import-roster
```

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
