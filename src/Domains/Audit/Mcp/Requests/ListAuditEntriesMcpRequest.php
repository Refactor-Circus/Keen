<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

/**
 * People who may not read the whole log still get the entries they made or
 * that are about them.
 */
final class ListAuditEntriesMcpRequest extends Request
{
    protected function rules(): array
    {
        return ListAuditEntriesData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $about = $this->allows('viewAny', AuditEntryModel::class) ? null : $this->actor();
        $entries = app(ListAuditEntriesAction::class)->execute(ListAuditEntriesData::fromValidated($validated), $about);

        return AuditEntryResource::collection($entries)->toStructuredResponse();
    }
}
