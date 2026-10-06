<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Http\Requests;

use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Resources\AuditEntryResource;

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
