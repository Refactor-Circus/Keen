<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Policies;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keen\Domains\Audit\Policies\AuditEntryPolicy;

/**
 * An application's own audit policy: the log reads as usual, but nobody
 * records entries through the API, MCP or the dashboard.
 */
final class ReadOnlyAuditPolicy extends AuditEntryPolicy
{
    public function create(Model $user): bool
    {
        return false;
    }
}
