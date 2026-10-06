<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Mcp\Requests;

use Illuminate\Database\Eloquent\Model;
use JayI\Foundation\Mcp\Requests\Request;
use JayI\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Resources\AuditEntryResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
