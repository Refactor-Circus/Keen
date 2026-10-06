<?php

declare(strict_types=1);

namespace JayI\Keen\Facades;

use Illuminate\Support\Facades\Facade;
use JayI\Keen\Domains\Audit\Data\PendingAuditEntry;

/**
 * @method static PendingAuditEntry record(string $action)
 *
 * @see \JayI\Keen\Keen
 */
class Keen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \JayI\Keen\Keen::class;
    }
}
