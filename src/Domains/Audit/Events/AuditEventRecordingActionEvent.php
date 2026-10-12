<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keen\Domains\Audit\Data\RecordAuditEventData;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;

/**
 * An application event is about to be recorded.
 */
final class AuditEventRecordingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public RecordAuditEventData $data,
    ) {}
}
