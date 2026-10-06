<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JayI\Keen\Domains\Audit\Http\Requests\IndexAuditRequest;
use JayI\Keen\Domains\Audit\Http\Requests\ShowAuditRequest;
use JayI\Keen\Domains\Audit\Http\Requests\StoreAuditRequest;

final class AuditController
{
    public function index(IndexAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function store(StoreAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }

    public function show(ShowAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
