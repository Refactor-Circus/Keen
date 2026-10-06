<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Resources\AuditEntryResource;
use Laravel\Mcp\ResponseFactory;

/**
 * People who may not read the whole log still get the entries they made or
 * that are about them.
 */
final class ListAuditEntriesMcpRequest extends Request
{
    protected function rules(): array
    {
        return ListAuditEntriesAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $about = $this->allows('viewAny', AuditEntryModel::class) ? null : $this->actor();
        $entries = app(ListAuditEntriesAction::class)->execute($validated, $about);

        return $this->structuredCollection(
            AuditEntryResource::collection($entries->items())->resolve(),
            ['next_cursor' => $entries->nextCursor()?->encode()],
        );
    }
}
