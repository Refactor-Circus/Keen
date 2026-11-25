<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RefactorCircus\Keen\Domains\Audit\Http\Requests\IndexAuditedPackageRequest;
use RefactorCircus\Keen\Domains\Audit\Http\Resources\AuditedPackageResource;

/**
 * The `@response` type names the payload the request's `persist()` builds,
 * for the OpenAPI document (openapi.json): the first docblock line is the
 * operation's summary.
 */
final class AuditedPackageController
{
    /**
     * List the audited packages whose history the caller may read.
     *
     * Sorted by label, then key. Keen itself and the application's own `app`
     * source are never listed.
     *
     * @response AnonymousResourceCollection<AuditedPackageResource>
     */
    public function index(IndexAuditedPackageRequest $request): JsonResponse
    {
        return $request->persist();
    }
}
