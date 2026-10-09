<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\IndexAuditRequest;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\ShowAuditRequest;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\StoreAuditRequest;

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
