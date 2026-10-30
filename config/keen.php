<?php

declare(strict_types=1);
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Policies\AuditEntryPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Recording
    |--------------------------------------------------------------------------
    |
    | Keen records every action of every package in the suite from the shared
    | action events, plus the events your application records itself with
    | Keen::record(). Reads (listing, showing) are never recorded.
    |
    | enabled:        Turn recording off without uninstalling Keen. Reading the
    |                 log keeps working.
    | retention_days: `php artisan keen:prune` deletes entries older than this;
    |                 schedule it. Null keeps entries forever.
    | redact:         Extra field names never written to the log. Passwords,
    |                 remember tokens, tokens, client secrets and private keys
    |                 are always redacted, as is anything a package redacts.
    | ignore:         Package keys (`impex`) or action event classes never
    |                 recorded.
    |
    | The log is append-only and hash-chained: `php artisan keen:verify`
    | reports the first entry that was altered after it was written.
    |
    */

    'enabled' => true,

    'retention_days' => 365,

    'redact' => [],

    'ignore' => [],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | The table each of Keen's records lives in: `entries` holds the log and
    | `chain` the head of its hash chain. Rename them here before running the
    | migrations; the model, the migration and the hash chain all read these
    | names. A published config that lists only some tables keeps the
    | defaults for the rest.
    |
    */

    'tables' => [
        'entries' => 'keen_entries',
        'chain' => 'keen_chain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | With authorization on, the JSON API, the MCP tools and the dashboard act
    | as the signed-in user and check the policies below. The bundled policy
    | defers to a `viewAuditLog` or `recordAuditEvent` Gate ability when the
    | application defines one, and lets anyone signed in through otherwise.
    | People may always read entries they made or that are about them.
    |
    | `policies` is the policy the Gate uses for each Keen model. Point a model
    | at a class of your own (extending the bundled one, or not) to change who
    | may read or record entries.
    |
    */

    'authorization' => false,

    'policies' => [
        AuditEntryModel::class => AuditEntryPolicy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'api/keen',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP
    |--------------------------------------------------------------------------
    */

    'mcp' => [
        'web' => [
            'enabled' => false,
            'route' => 'mcp/keen',
            'middleware' => ['api'],
        ],
        'local' => [
            'enabled' => false,
            'handle' => 'keen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cortex
    |--------------------------------------------------------------------------
    |
    | With refactor-circus/cortex installed, Cortex agents can read and record audit
    | entries. `tools` limits which tools are offered (null offers all).
    |
    */

    'cortex' => [
        'enabled' => true,
        'server' => 'keen',
        'tools' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Atrium
    |--------------------------------------------------------------------------
    |
    | With refactor-circus/atrium installed, the audit log gets its own section in the
    | dashboard, and every package's screens show their own history.
    |
    */

    'ui' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Atrium history
    |--------------------------------------------------------------------------
    |
    | Every package that audits gets its own history page in the dashboard,
    | at `atrium.keen.history.show` (/atrium/history/{package}), a "History"
    | nav item linking to it, and a History section on its Atrium screens.
    | Packages declare nothing for this.
    |
    | screens:     The screens the History section attaches to: `*` (every
    |              screen), `{package}.*` (every screen of a package) or an
    |              exact screen key such as `shop.product`. On a detail
    |              screen it shows that record's history; on a list screen,
    |              the package's.
    | slot:        The slot it renders in, on every screen declaring it.
    | exclude:     Screen-key patterns (Str::is) it never shows on. Keen's
    |              own screens and Atrium's dashboard and settings screens
    |              are workspace chrome, not records.
    | tabs:        Screens that get an "Activity" tab: screen key =>
    |              ['scope' => true] lists what was recorded in the screen's
    |              record (an organization, say), ['scope' => false] the
    |              record's own history. Only the host's own check on the
    |              screen keeps one record's activity from another's viewers.
    | nav.enabled: Add the "History" items at all.
    | nav.sort:    Their position in the sidebar.
    | nav.exclude: Package keys that get no item.
    | nav.groups:  Package key => the nav group its item sits in, as a
    |              translation key or a plain label; the package's label
    |              otherwise.
    |
    | The section, a tab and an item show only while the package audits and
    | its history rule lets the viewer read it. `screens`, `slot` and `tabs`
    | are read once at boot; `exclude` and `nav` each time they are used.
    |
    | A bad target or slot throws an InvalidArgumentException at boot in the
    | local and testing environments. Elsewhere it is reported once and
    | skipped, and the rest registers: a bad slot skips the section on every
    | screen, but not the tabs.
    |
    */

    'atrium' => [
        'history' => [
            'screens' => ['*'],
            'slot' => 'after',
            'exclude' => ['keen.*', 'atrium.*'],
            'tabs' => [
                'roster.organization' => ['scope' => true],
            ],
            'nav' => [
                'enabled' => true,
                'sort' => 90,
                'exclude' => [],
                'groups' => [
                    'showroom' => 'showroom::showroom.catalog',
                    'pennantplus' => 'pennantplus::pennantplus.group',
                ],
            ],
        ],
    ],

];
