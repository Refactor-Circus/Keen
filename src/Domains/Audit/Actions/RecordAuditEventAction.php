<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Keen\Domains\Audit\Data\RecordAuditEventData;
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
 * JSON API and MCP never allow. Pass `$subject` / `$scope` models from code, or the subject
 * fields of RecordAuditEventData from the API and MCP.
 */
final class RecordAuditEventAction extends Action
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    protected function handle(RecordAuditEventData $data, ?Model $actor = null, ?Model $subject = null, ?Model $scope = null, string $source = AuditEntryModel::SOURCE_APP): AuditEntryModel
    {
        AuditEventRecordingActionEvent::dispatch($data);

        $entry = DB::transaction(fn (): AuditEntryModel => $this->perform($data, $actor, $subject, $scope, $source));

        AuditEventRecordedActionEvent::dispatch($entry);

        return $entry;
    }

    private function perform(RecordAuditEventData $data, ?Model $actor, ?Model $subject, ?Model $scope, string $source): AuditEntryModel
    {
        return $this->recorder->write(
            source: $source,
            action: $data->action,
            subject: $subject,
            changes: $data->changes,
            context: $data->context,
            scope: $scope,
            actor: $actor,
            subjectType: $this->filled($data->subjectType),
            subjectId: $this->filled($data->subjectId),
            subjectLabel: $this->filled($data->subjectLabel),
        );
    }

    private function filled(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
