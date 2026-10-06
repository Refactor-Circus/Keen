<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use JayI\Foundation\Mcp\Tool;
use JayI\Keen\Domains\Audit\Mcp\Requests\ShowAuditEntryMcpRequest;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

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
