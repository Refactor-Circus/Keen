<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Actions;

use Illuminate\Database\Eloquent\Model;
use JayI\Foundation\Actions\Action;
use JayI\Keen\Domains\Audit\Events\AuditEventRecordedActionEvent;
use JayI\Keen\Domains\Audit\Events\AuditEventRecordingActionEvent;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Services\AuditRecorder;

/**
 * Record one of the application's own events, such as `invoice.paid`.
 *
 * Always recorded with source `app`, so it can never pass for an entry a
 * package made. Pass `$subject` / `$scope` models from code, or `subject_*`
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
    protected function handle(array $data, ?Model $actor = null, ?Model $subject = null, ?Model $scope = null): AuditEntryModel
    {
        AuditEventRecordingActionEvent::dispatch($data);

        $entry = $this->perform($data, $actor, $subject, $scope);

        AuditEventRecordedActionEvent::dispatch($entry);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function perform(array $data, ?Model $actor, ?Model $subject, ?Model $scope): AuditEntryModel
    {
        /** @var array<string, array{0: mixed, 1: mixed}> $changes */
        $changes = (array) ($data['changes'] ?? []);

        /** @var array<string, mixed> $context */
        $context = (array) ($data['context'] ?? []);

        return $this->recorder->write(
            source: AuditEntryModel::SOURCE_APP,
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
