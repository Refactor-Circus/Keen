<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use RefactorCircus\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;
use RefactorCircus\Keystone\Http\Requests\Request;

final class StoreAuditRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', AuditEntryModel::class);
    }

    public function rules(): array
    {
        return RecordAuditEventAction::rules();
    }

    public function persist(): JsonResponse
    {
        $user = $this->user();
        $entry = app(RecordAuditEventAction::class)->execute($this->validated(), $user instanceof Model ? $user : null);

        return (new AuditEntryResource($entry))->response()->setStatusCode(201);
    }
}
