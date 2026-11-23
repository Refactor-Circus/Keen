<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Pagination\CursorPaginator;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\IndexAuditRequest;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\ShowAuditRequest;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\StoreAuditRequest;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;

/**
 * The `@response` types name the payload each request's `persist()` builds,
 * for the OpenAPI document (openapi.json): the first docblock line is the
 * operation's summary.
 */
final class AuditController
{
    /**
     * List audit entries, newest first, a cursor page at a time.
     *
     * @response AnonymousResourceCollection<CursorPaginator<AuditEntryResource>>
     */
    public function index(IndexAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }

    /**
     * Record an audit entry from outside the application's actions.
     *
     * @response JsonResponse<ResourceResponse<AuditEntryResource>, 201>
     */
    public function store(StoreAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }

    /**
     * Show one audit entry.
     *
     * @response AuditEntryResource
     */
    public function show(ShowAuditRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
