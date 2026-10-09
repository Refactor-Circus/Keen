<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Actions;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEventRecordedActionEvent;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEventRecordingActionEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Services\AuditRecorder;
use RefactorCircus\Keystone\Actions\Action;

/**
 * Record one of the application's own events, such as `invoice.paid`.
 *
 * Recorded with source `app`, so it can never pass for an entry a package
 * made - unless code records a package's own event with `source()`, which the
 * JSON API and MCP never allow. Pass `$subject` / `$scope` models from code, or `subject_*`
 * in `$data` from the API and MCP.
 */
final class RecordAuditEventAction extends Action
{
    public function __construct(private readonly AuditRecorder $recorder) {}

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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handle(array $data, ?Model $actor = null, ?Model $subject = null, ?Model $scope = null, string $source = AuditEntryModel::SOURCE_APP): AuditEntryModel
    {
        AuditEventRecordingActionEvent::dispatch($data);

        $entry = $this->perform($data, $actor, $subject, $scope, $source);

        AuditEventRecordedActionEvent::dispatch($entry);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(array $data, ?Model $actor, ?Model $subject, ?Model $scope, string $source): AuditEntryModel
    {
        /** @var array<string, array{0: mixed, 1: mixed}> $changes */
        $changes = (array) ($data['changes'] ?? []);

        /** @var array<string, mixed> $context */
        $context = (array) ($data['context'] ?? []);

        return $this->recorder->write(
            source: $source,
            action: (string) $data['action'],
            subject: $subject,
            changes: $changes,
            context: $context,
            scope: $scope,
            actor: $actor,
            subjectType: $this->string($data['subject_type'] ?? null),
            subjectId: $this->string($data['subject_id'] ?? null),
            subjectLabel: $this->string($data['subject_label'] ?? null),
        );
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
