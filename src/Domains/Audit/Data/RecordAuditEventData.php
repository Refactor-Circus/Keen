<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Data;

use RefactorCircus\Keystone\Data\Data;

/**
 * Input for RecordAuditEventAction: a dotted lower-case action such as
 * `invoice.paid`, optionally the record it happened to (by morph type, key
 * and label, from the API and MCP), field changes as `field => [old, new]`,
 * and extra context to keep with the entry.
 */
final readonly class RecordAuditEventData extends Data
{
    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $action,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $subjectLabel = null,
        public array $changes = [],
        public array $context = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'changes' => ['sometimes', 'array'],
            'changes.*' => ['array', 'size:2'],
            'context' => ['sometimes', 'array'],
        ];
    }
}
