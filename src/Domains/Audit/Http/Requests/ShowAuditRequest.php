<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Foundation\Http\Requests\Request;
use RefactorCircus\Keen\Domains\Audit\Actions\ShowAuditEntryAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;

final class ShowAuditRequest extends Request
{
    private ?AuditEntryModel $resolvedEntry = null;

    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->entry());
    }

    public function rules(): array
    {
        return ShowAuditEntryAction::rules();
    }

    public function persist(): JsonResponse
    {
        return (new AuditEntryResource(app(ShowAuditEntryAction::class)->execute($this->entry())))->response();
    }

    private function entry(): AuditEntryModel
    {
        return $this->resolvedEntry ??= AuditEntryModel::query()->whereKey($this->route('entry'))->firstOrFail();
    }
}
