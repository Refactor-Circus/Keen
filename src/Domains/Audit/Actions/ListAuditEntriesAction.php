<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JayI\Foundation\Actions\Action;
use JayI\Keen\Domains\Audit\Events\AuditEntriesListedActionEvent;
use JayI\Keen\Domains\Audit\Events\AuditEntriesListingActionEvent;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * The audit log, newest first, cursor paginated.
 *
 * Filters: `source` (a package key or `app`), `action` (exact, or a prefix
 * ending in `.`, such as `product.`), `subject_type` + `subject_id`,
 * `actor_type` + `actor_id`, `scope_type` + `scope_id`, `since`, `until`.
 */
final class ListAuditEntriesAction extends Action
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'source' => ['sometimes', 'nullable', 'string', 'max:32'],
            'action' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actor_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actor_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scope_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scope_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'since' => ['sometimes', 'nullable', 'date'],
            'until' => ['sometimes', 'nullable', 'date'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  Model|null  $about  Only entries made by or about this model, for people who may read only their own.
     * @return CursorPaginator<int, AuditEntryModel>
     */
    protected function handle(array $filters = [], ?Model $about = null): CursorPaginator
    {
        AuditEntriesListingActionEvent::dispatch($filters);

        $entries = $this->perform($filters, $about);

        AuditEntriesListedActionEvent::dispatch($filters);

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, AuditEntryModel>
     */
    private function perform(array $filters, ?Model $about): CursorPaginator
    {
        $query = AuditEntryModel::query();

        if ($about !== null) {
            $query->about($about);
        }

        foreach (['source', 'subject_type', 'subject_id', 'actor_type', 'actor_id', 'scope_type', 'scope_id'] as $column) {
            $value = $this->string($filters, $column);

            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        $action = $this->string($filters, 'action');

        if ($action !== null) {
            str_ends_with($action, '.') ? $query->where('action', 'like', $action.'%') : $query->where('action', $action);
        }

        $this->between($query, $this->string($filters, 'since'), $this->string($filters, 'until'));

        $perPage = is_numeric($filters['per_page'] ?? null) ? (int) $filters['per_page'] : 25;

        return $query->orderByDesc('id')->cursorPaginate($perPage, ['*'], 'cursor', $this->string($filters, 'cursor'));
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
     * @param  array<string, mixed>  $filters
     */
    private function string(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
