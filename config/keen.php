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
    | Authorization
    |--------------------------------------------------------------------------
    |
    | With authorization on, the JSON API, the MCP tools and the dashboard act
    | as the signed-in user and check the policies below. The bundled policy
    | defers to a `viewAuditLog` or `recordAuditEvent` Gate ability when the
    | application defines one, and lets anyone signed in through otherwise.
    | People may always read entries they made or that are about them.
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

];
