<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Requests\ListAuditEntriesMcpRequest;

#[Description('List the audit log across every package, newest first: who did what to which record, through which surface, and which fields changed. Cursor paginated.')]
final class ListAuditEntriesTool extends Tool
{
    public function handle(ListAuditEntriesMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->description('Only entries from this package (showroom, roster, ...) or app for the application\'s own.'),
            'action' => $schema->string()->description('Only this action (product.updated), or a prefix ending in a dot (product.).'),
            'subject_type' => $schema->string()->description('Morph type of the record, with subject_id.'),
            'subject_id' => $schema->string()->description('Key of the record, with subject_type.'),
            'actor_type' => $schema->string()->description('Morph type of who acted, with actor_id.'),
            'actor_id' => $schema->string()->description('Key of who acted.'),
            'scope_type' => $schema->string()->description('Morph type of the scope (an organization), with scope_id.'),
            'scope_id' => $schema->string()->description('Key of the scope.'),
            'since' => $schema->string()->description('Only entries at or after this date and time.'),
            'until' => $schema->string()->description('Only entries at or before this date and time.'),
            'cursor' => $schema->string()->description('Cursor from a previous page (next_cursor).'),
            'per_page' => $schema->integer()->description('Results per page, up to 100.')->min(1)->max(100),
        ];
    }
}
