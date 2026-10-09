<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * An application event was recorded.
 */
final class AuditEventRecordedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public AuditEntryModel $entry,
    ) {}
}
