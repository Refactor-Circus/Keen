<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * The AuditEntryModel `saving` Eloquent event.
 */
final class AuditEntrySavingEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public AuditEntryModel $entry) {}

    public function model(): Model
    {
        return $this->entry;
    }

    public function hook(): string
    {
        return 'saving';
    }
}
