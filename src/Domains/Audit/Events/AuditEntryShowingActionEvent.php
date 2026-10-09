<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * An audit entry is about to be shown.
 */
final class AuditEntryShowingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public AuditEntryModel $entry,
    ) {}
}
