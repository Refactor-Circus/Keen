<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEntriesListedActionEvent;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEntriesListingActionEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Services\StoredKeys;
use RefactorCircus\Keystone\Actions\Action;

/**
 * The audit log, newest first, cursor paginated, filtered by
 * ListAuditEntriesData: source (a package key or `app`), action (exact, or a
 * prefix ending in `.`, such as `product.`), subject, actor, scope and time.
 *
 * A typed `subject_id` or `scope_id` is the record's route key (what a screen
 * passes, such as an organization's slug) or its key, matched against the key
 * entries store (D-019).
 */
final class ListAuditEntriesAction extends Action
{
    public function __construct(private readonly StoredKeys $keys) {}

    /**
     * @param  Model|null  $about  Only entries made by or about this model, for people who may read only their own.
     * @return CursorPaginator<int, AuditEntryModel>
     */
    protected function handle(ListAuditEntriesData $data = new ListAuditEntriesData, ?Model $about = null): CursorPaginator
    {
        AuditEntriesListingActionEvent::dispatch($data);

        $entries = $this->perform($data, $about);

        AuditEntriesListedActionEvent::dispatch($data);

        return $entries;
    }

    /**
     * @return CursorPaginator<int, AuditEntryModel>
     */
    private function perform(ListAuditEntriesData $data, ?Model $about): CursorPaginator
    {
        $query = AuditEntryModel::query();

        if ($about !== null) {
            $query->about($about);
        }

        $columns = [
            'source' => $data->source,
            'subject_type' => $data->subjectType,
            'subject_id' => $this->storedKey($data->subjectType, $data->subjectId),
            'actor_type' => $data->actorType,
            'actor_id' => $data->actorId,
            'scope_type' => $data->scopeType,
            'scope_id' => $this->storedKey($data->scopeType, $data->scopeId),
        ];

        foreach ($columns as $column => $value) {
            if ($this->filled($value)) {
                $query->where($column, $value);
            }
        }

        $action = $data->action;

        if ($this->filled($action)) {
            str_ends_with($action, '.') ? $query->where('action', 'like', $action.'%') : $query->where('action', $action);
        }

        $this->between($query, $this->filled($data->since) ? $data->since : null, $this->filled($data->until) ? $data->until : null);

        return $query->orderByDesc('id')->cursorPaginate($data->perPage, ['*'], 'cursor', $this->filled($data->cursor) ? $data->cursor : null);
    }

    /**
     * @param  Builder<AuditEntryModel>  $query
     */
    private function between(Builder $query, ?string $since, ?string $until): void
    {
        if ($since !== null) {
            $query->where('created_at', '>=', Carbon::parse($since));
        }

        if ($until !== null) {
            $query->where('created_at', '<=', Carbon::parse($until));
        }
    }

    /**
     * The key entries store for a record a type and identifier name; an
     * untyped identifier as given.
     */
    private function storedKey(?string $type, ?string $id): ?string
    {
        return $this->filled($type) && $this->filled($id) ? $this->keys->for($type, $id) : $id;
    }

    /**
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function filled(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
