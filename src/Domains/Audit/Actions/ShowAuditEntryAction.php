<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Actions;

use RefactorCircus\Foundation\Actions\Action;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEntryShowingActionEvent;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEntryShownActionEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

final class ShowAuditEntryAction extends Action
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [];
    }

    protected function handle(AuditEntryModel $entry): AuditEntryModel
    {
        AuditEntryShowingActionEvent::dispatch($entry);

        $entry->loadMissing(['actor']);

        AuditEntryShownActionEvent::dispatch($entry);

        return $entry;
    }
}
