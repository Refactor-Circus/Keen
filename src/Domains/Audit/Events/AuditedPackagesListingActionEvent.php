<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;
use RefactorCircus\Keystone\Contracts\ReadActionEvent;

/**
 * The audited packages are about to be listed.
 */
final class AuditedPackagesListingActionEvent implements ActionStartingEvent, ReadActionEvent
{
    use Dispatchable;
    use SerializesModels;
}
