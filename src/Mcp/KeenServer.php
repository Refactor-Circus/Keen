<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Mcp;

use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\RecordAuditEventTool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ShowAuditEntryTool;
use RefactorCircus\Keystone\Mcp\Server;

#[Name('Keen')]
#[Version('1.0.0')]
#[Instructions(
    'Read the tamper-evident audit log of every Refactor Circus package. Each entry records its source (the package, or app), '.
    'an action such as product.updated, who acted, the record it is about, the surface it came through (atrium, http, '.
    'mcp, cortex, cli, code), and the fields that changed as field => [old, new]. Secrets show as [redacted]. '.
    'Record the application\'s own events with record-audit-event-tool. Listings are cursor paginated: pass next_cursor '.
    'back as cursor for the next page.',
)]
final class KeenServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    public const array TOOLS = [
        ListAuditEntriesTool::class,
        ShowAuditEntryTool::class,
        RecordAuditEventTool::class,
    ];

    /**
     * @var array<int, class-string<Tool>|Tool>
     */
    protected array $tools = self::TOOLS;
}
