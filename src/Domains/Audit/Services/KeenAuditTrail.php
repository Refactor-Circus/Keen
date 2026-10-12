<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Services;

use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditFilter;
use RefactorCircus\Keystone\Audit\Data\AuditPage;

/**
 * The audit trail every package and the Atrium dashboard read through, once
 * Keen is installed.
 */
final readonly class KeenAuditTrail implements AuditTrail
{
    public function __construct(private ListAuditEntriesAction $entries) {}

    public function available(): bool
    {
        return true;
    }

    public function entries(AuditFilter $filter): AuditPage
    {
        $page = $this->entries->execute(new ListAuditEntriesData(
            source: $filter->source,
            action: $filter->action,
            subjectType: $filter->subjectType,
            subjectId: $filter->subjectId,
            actorId: $filter->actorId,
            scopeType: $filter->scopeType,
            scopeId: $filter->scopeId,
            perPage: $filter->limit,
            cursor: $filter->cursor,
        ));

        return new AuditPage(
            array_values(array_map(fn (AuditEntryModel $entry) => $entry->toAuditEntry(), $page->items())),
            $page->nextCursor()?->encode(),
        );
    }
}
