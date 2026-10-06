<?php

declare(strict_types=1);

namespace JayI\Keen;

use JayI\Keen\Domains\Audit\Data\PendingAuditEntry;

/**
 * Keen's entry point for application code.
 */
class Keen
{
    /**
     * Start one of the application's own audit entries:
     *
     *     Keen::record('invoice.paid')->on($invoice)->with(['amount' => 100])->save();
     */
    public function record(string $action): PendingAuditEntry
    {
        return new PendingAuditEntry($action);
    }
}
