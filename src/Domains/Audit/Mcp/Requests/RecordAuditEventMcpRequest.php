<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Requests;

use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Foundation\Mcp\Requests\Request;
use RefactorCircus\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;

final class RecordAuditEventMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', AuditEntryModel::class);
    }

    protected function rules(): array
    {
        return RecordAuditEventAction::rules();
    }

    protected function handle(array $validated): ResponseFactory
    {
        $user = $this->user();
        $entry = app(RecordAuditEventAction::class)->execute($validated, $user instanceof Model ? $user : null);

        return Response::structured((new AuditEntryResource($entry))->resolve());
    }
}
