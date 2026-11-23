<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\IndexAuditedPackageRequest;

final class AuditedPackageController
{
    public function index(IndexAuditedPackageRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
