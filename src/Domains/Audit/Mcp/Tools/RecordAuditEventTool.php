<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use RefactorCircus\Foundation\Mcp\Tool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Requests\RecordAuditEventMcpRequest;

#[Description('Record one of the application\'s own events in the audit log, such as invoice.paid. Always recorded with source app.')]
final class RecordAuditEventTool extends Tool
{
    public function handle(RecordAuditEventMcpRequest $request): Response|ResponseFactory
    {
        return $request->persist();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Dotted lower-case action name, such as invoice.paid.')->required(),
            'subject_type' => $schema->string()->description('Morph type of the record it happened to.'),
            'subject_id' => $schema->string()->description('Key of the record it happened to.'),
            'subject_label' => $schema->string()->description('How to name the record in the log.'),
            'changes' => $schema->object()->description('Field changes, as field => [old, new].'),
            'context' => $schema->object()->description('Extra details to keep with the entry.'),
        ];
    }
}
