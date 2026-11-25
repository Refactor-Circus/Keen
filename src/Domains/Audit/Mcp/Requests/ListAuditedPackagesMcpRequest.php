<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Mcp\Requests;

use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditedPackagesAction;
use RefactorCircus\Keen\Domains\Audit\Http\Resources\AuditedPackageResource;
use RefactorCircus\Keystone\Mcp\Requests\Request;

/**
 * Each package's own history rule decides whether it is listed, so the
 * action gets the raw user, as Keystone's history request passes it.
 */
final class ListAuditedPackagesMcpRequest extends Request
{
    protected function handle(array $validated): ResponseFactory
    {
        $packages = app(ListAuditedPackagesAction::class)->execute($this->user());

        return AuditedPackageResource::collection($packages)->toStructuredResponse();
    }
}
