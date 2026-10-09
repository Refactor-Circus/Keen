<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;

/**
 * People who may not read the whole log still get the entries they made or
 * that are about them.
 */
final class IndexAuditRequest extends Request
{
    public function rules(): array
    {
        return ListAuditEntriesAction::rules();
    }

    public function persist(): JsonResponse
    {
        $about = $this->allows('viewAny', AuditEntryModel::class) ? null : $this->actor();

        $entries = app(ListAuditEntriesAction::class)->execute($this->validated(), $about);

        return AuditEntryResource::collection($entries)->response();
    }
}
