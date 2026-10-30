<p align="center">
    <img src="art/icon.png" width="160" alt="Keen icon">
</p>

# Keen

A tamper-evident audit log for every package of the Refactor Circus suite, and for your application's own events.

Install Keen and every action of every suite package (Atrium, Cortex, Impex, PennantPlus, Polycart, Roster, Showroom) is recorded: who did it, to which record, through which surface (`atrium`, `http`, `mcp`, `cortex`, `cli`, `code`), and which fields changed. No package depends on Keen; each announces its actions through the shared events of [refactor-circus/keystone](https://github.com/Refactor-Circus/Keystone), and Keen listens.

- **Append-only and hash-chained.** Each entry's hash covers the one before it, so editing or removing any entry breaks the chain. `php artisan keen:verify` finds the first altered entry.
- **Secrets never reach the log.** Passwords, tokens, client secrets and private keys are redacted, as is anything a package or your config names; a redacted field still shows that it changed.
- **API, MCP and Atrium parity.** Read and record entries over the JSON API, the MCP server (and so from Cortex agents), or the Atrium dashboard.
- **Every package keeps its own history.** Once Keen is installed, each package's `GET {prefix}/history` endpoint and `list-{package}-history-tool` MCP tool show its own entries, and with Atrium installed Keen adds a History page, a History nav item and a History section to its screens. Packages declare nothing for this.

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
| JSON API (`keen.routes`) | `GET api/keen/entries`, `GET api/keen/entries/{id}` | `POST api/keen/entries` |
| MCP (`keen.mcp`) | `list-audit-entries-tool`, `show-audit-entry-tool` | `record-audit-event-tool` |
| Atrium | **Audit log** in the sidebar | the **Record an event** form |

Filters: `source` (a package key or `app`), `action` (exact, or a prefix ending in `.`), `subject_type` + `subject_id`, `actor_type` + `actor_id`, `scope_type` + `scope_id`, `since`, `until`. Listings are cursor paginated: pass `per_page` (up to 100) and the previous page's next cursor (`meta.next_cursor`) as `cursor`.

## History in Atrium

Atrium is optional: Keen suggests it and never requires it. With Atrium installed and `keen.ui.enabled` on, Keen brings every package's history to the dashboard by itself. Packages add no component, route or nav item for it.

- **History page.** `GET /atrium/history/{package}` (route `atrium.keen.history.show`) lists that package's entries, newest first, filtered by `action`, `subject_type` and `subject_id`, and cursor paginated (`per_page`, `cursor`). It answers 404 for an unknown package or one that doesn't audit, and 403 when the package's history rule refuses the viewer.
- **History nav items.** Every registered package except Keen gets a "History" item linking to its page. It sits in the package's own nav group, and shows only while the package audits and its history rule lets the viewer read it.
- **History section.** A "History" section attaches to every Atrium screen (`*`), in the `after` slot. On a detail screen it shows the record's history; on a list screen, or a detail screen without a record, the package's whole history. It is hidden on Keen's own screens and Atrium's dashboard and settings screens, on screens of packages that don't audit, and from viewers the package's history rule refuses.
- **Activity tab.** Screens named in `keen.atrium.history.tabs` get an "Activity" tab (`?tab=keen.activity`). Scoped, it lists what was recorded in the screen's record, such as everything that happened in an organization (`->in($organization)`); unscoped, the record's own history. A viewer the history rule refuses doesn't see the tab, and gets a 403 for `?tab=keen.activity`.

The History section and the "view all" link read the subject's type as its morph class, so a model with no morph map entry puts its full class name into the link. A record that isn't an audited subject of its screen's package shows an empty history.

Configure all of it under `keen.atrium.history`:

```php
'atrium' => [
    'history' => [
        'screens' => ['*'],                    // '*', '{package}.*' or exact screen keys
        'slot' => 'after',                     // the slot the section renders in
        'exclude' => ['keen.*', 'atrium.*'],   // screen-key patterns it never shows on (Str::is)
        'tabs' => ['roster.organization' => ['scope' => true]],
        'nav' => [
            'enabled' => true,
            'sort' => 90,
            'exclude' => [],                   // package keys that get no item
            'groups' => [                      // package key => nav group; the package's label otherwise
                'showroom' => 'showroom::showroom.catalog',
                'pennantplus' => 'pennantplus::pennantplus.group',
            ],
        ],
    ],
],
```

`screens`, `slot` and `tabs` are read once at boot; `exclude` and `nav` each time they are used. A bad screen target (in `screens` or as a `tabs` key) or a bad `slot` throws an `InvalidArgumentException` naming the key and the value at boot in the `local` and `testing` environments. In any other environment it is reported once through your exception handler and skipped, the valid entries still register, and the application boots. A bad `slot` skips the section on every screen, but the tabs still register.

Your application's own views can still place a trail where they like with Keen's component, which renders nothing while the package doesn't audit or its history rule refuses the viewer:

```blade
<x-keen::audit-trail source="showroom" :subject="$product" />
```

Keen's components are `x-keen::audit-trail` (`source`, `subject`, `scope`, `action`, `limit`, `title`), `x-keen::audit.entries` and `x-keen::audit.changes`.

Keen needs an Atrium with the screen registry: `composer.json` conflicts with `refactor-circus/atrium` below `0.2.0`, and an older Atrium that is installed anyway boots without the History section and tabs.

## Authorization

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

`composer test` also runs `composer test:without-atrium`, a suite that boots Keen with Atrium's classes made unloadable in-process. That run can't see an Atrium class leaking in through a classmap or Composer `files` autoload. The authoritative proof that Keen runs without Atrium is the `without-atrium` CI job, which uninstalls Atrium (`composer remove --dev refactor-circus/atrium --no-update`) before running the same suite.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
