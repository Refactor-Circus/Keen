<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Requests;

use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use RefactorCircus\Keen\Domains\Audit\Data\RecordAuditEventData;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

final class RecordAuditEventMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', AuditEntryModel::class);
    }

    protected function rules(): array
    {
        return RecordAuditEventData::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $user = $this->user();
        $entry = app(RecordAuditEventAction::class)->execute(RecordAuditEventData::fromValidated($validated), $user instanceof Model ? $user : null);

        return (new AuditEntryResource($entry))->toStructuredResponse();
    }
}
