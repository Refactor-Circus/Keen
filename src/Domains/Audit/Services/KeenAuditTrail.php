<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Services;

use JayI\Foundation\Audit\Contracts\AuditTrail;
use JayI\Foundation\Audit\Data\AuditFilter;
use JayI\Foundation\Audit\Data\AuditPage;
use JayI\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;

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
        $page = $this->entries->execute(array_filter([
            'source' => $filter->source,
            'subject_type' => $filter->subjectType,
            'subject_id' => $filter->subjectId,
            'action' => $filter->action,
            'actor_id' => $filter->actorId,
            'cursor' => $filter->cursor,
            'per_page' => $filter->limit,
        ], fn (mixed $value): bool => $value !== null));

        return new AuditPage(
            array_values(array_map(fn (AuditEntryModel $entry) => $entry->toAuditEntry(), $page->items())),
            $page->nextCursor()?->encode(),
        );
    }
}
