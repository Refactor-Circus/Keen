<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Data;

use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Input for ListAuditEntriesAction: the filters and the page to show.
 *
 * `action` matches exactly, or as a prefix when it ends in `.` (`product.`).
 * `since` and `until` bound the entries' time, inclusive.
 */
final readonly class ListAuditEntriesData extends Data
{
    public function __construct(
        public ?string $source = null,
        public ?string $action = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $scopeType = null,
        public ?string $scopeId = null,
        public ?string $since = null,
        public ?string $until = null,
        public int $perPage = 25,
        public ?string $cursor = null,
    ) {}

    /**
     * How the list pages; its tool states the same limits.
     */
    public static function pagination(): Pagination
    {
        return Pagination::cursor(default: 25);
    }

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
        ] + self::pagination()->rules();
    }
}
