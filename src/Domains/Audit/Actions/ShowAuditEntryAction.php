<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Actions;

use JayI\Foundation\Actions\Action;
use JayI\Keen\Domains\Audit\Events\AuditEntryShowingActionEvent;
use JayI\Keen\Domains\Audit\Events\AuditEntryShownActionEvent;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;

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
