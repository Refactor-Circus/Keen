<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use JayI\Foundation\Http\Requests\Request;
use JayI\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Resources\AuditEntryResource;

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
