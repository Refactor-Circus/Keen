<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;
use RefactorCircus\Keystone\Contracts\ReadActionEvent;

/**
 * Audit entries were listed.
 */
final class AuditEntriesListedActionEvent implements ActionFinishedEvent, ReadActionEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ListAuditEntriesData $data,
    ) {}
}
