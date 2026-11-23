<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;
use RefactorCircus\Keystone\Contracts\ReadActionEvent;

/**
 * The audited packages were listed.
 */
final class AuditedPackagesListedActionEvent implements ActionFinishedEvent, ReadActionEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $keys  The listed packages' keys, in order.
     */
    public function __construct(
        public array $keys,
    ) {}
}
