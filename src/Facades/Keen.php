<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Facades;

use Illuminate\Support\Facades\Facade;
use RefactorCircus\Keen\Domains\Audit\Data\PendingAuditEntry;

/**
 * @method static PendingAuditEntry record(string $action)
 *
 * @see \RefactorCircus\Keen\Keen
 */
class Keen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \RefactorCircus\Keen\Keen::class;
    }
}
