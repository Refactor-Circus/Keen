<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditedPackagesAction;
use RefactorCircus\Keen\Domains\Audit\Http\Resources\AuditedPackageResource;
use RefactorCircus\Keystone\Http\Requests\Request;

/**
 * Each package's own history rule decides whether it is listed, so the
 * action gets the raw user, as Keystone's history request passes it.
 */
final class IndexAuditedPackageRequest extends Request
{
    public function persist(): JsonResponse
    {
        $packages = app(ListAuditedPackagesAction::class)->execute($this->user());

        return AuditedPackageResource::collection($packages)->response();
    }
}
