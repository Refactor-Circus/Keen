<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Requests\ShowAuditEntryMcpRequest;

#[Description('Show one audit entry by id, with its field changes, context and hash.')]
final class ShowAuditEntryTool extends Tool
{
    public function handle(ShowAuditEntryMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The entry id.')->required(),
        ];
    }
}
