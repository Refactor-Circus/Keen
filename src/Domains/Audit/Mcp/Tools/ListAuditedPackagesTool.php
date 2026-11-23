<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Keen\Domains\Audit\Http\Resources\AuditedPackageResource;
use RefactorCircus\Keen\Domains\Audit\Mcp\Requests\ListAuditedPackagesMcpRequest;
use RefactorCircus\Keystone\Mcp\Tool;

#[Description('List the packages whose audit history you may read, by key and label, sorted by label. Pass a key as source to list-audit-entries-tool for that package\'s entries.')]
final class ListAuditedPackagesTool extends Tool
{
    public function handle(ListAuditedPackagesMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return AuditedPackageResource::collectionSchema($schema);
    }
}
