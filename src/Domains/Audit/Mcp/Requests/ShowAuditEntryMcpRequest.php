<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Mcp\Requests;

use JayI\Foundation\Mcp\Requests\Request;
use JayI\Keen\Domains\Audit\Actions\ShowAuditEntryAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Resources\AuditEntryResource;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class ShowAuditEntryMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return parent::authorize() && $this->allows('view', $this->entry());
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'integer']];
    }

    protected function handle(array $validated): ResponseFactory
    {
        return Response::structured((new AuditEntryResource(app(ShowAuditEntryAction::class)->execute($this->entry())))->resolve());
    }

    private function entry(): AuditEntryModel
    {
        return AuditEntryModel::query()->whereKey($this->get('id'))->firstOrFail();
    }
}
