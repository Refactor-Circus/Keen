<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Keen\Domains\Audit\Services\AuditLog;
use stdClass;

/**
 * Copy the audit log Roster kept before Keen into Keen's log.
 *
 * Entries are appended in their original order, so they join Keen's hash
 * chain; Roster's own chain is checked first and the import refuses a log
 * that was altered. Organizations become the entry's scope.
 */
final class ImportRosterAuditCommand extends Command
{
    protected $signature = 'keen:import-roster
        {--organization-type= : Morph type to record as the scope of entries with an organization (default: Roster\'s organization model)}';

    protected $description = 'Copy the audit log Roster kept before Keen into Keen';

    public function handle(AuditLog $log): int
    {
        if (! Schema::hasTable('roster_audit_entries')) {
            $this->components->info('No Roster audit log to import.');

            return self::SUCCESS;
        }

        if (DB::table('keen_entries')->where('context->imported_from', 'roster')->exists()) {
            $this->components->error('The Roster audit log was imported already.');

            return self::FAILURE;
        }

        $users = config('auth.providers.users.model');
        $actorType = is_string($users) && is_a($users, Model::class, true) ? (new $users)->getMorphClass() : null;
        $organizationType = $this->option('organization-type');
        $organizationType = is_string($organizationType) && $organizationType !== '' ? $organizationType : 'RefactorCircus\Roster\Domains\Organization\Models\OrganizationModel';
        $imported = 0;

        DB::table('roster_audit_entries')->orderBy('id')->chunkById(500, function (Collection $entries) use ($log, $actorType, $organizationType, &$imported): void {
            /** @var stdClass $entry */
            foreach ($entries as $entry) {
                $context = $this->json($entry->context);
                $context['imported_from'] = 'roster';
                $context['roster_entry_id'] = $entry->id;

                $log->append([
                    'source' => $entry->source === 'app' ? 'app' : 'roster',
                    'action' => $entry->action,
                    'actor_type' => $entry->actor_id === null ? null : $actorType,
                    'actor_id' => $entry->actor_id === null ? null : (string) $entry->actor_id,
                    'subject_type' => $entry->subject_type,
                    'subject_id' => $entry->subject_id,
                    'subject_label' => $entry->subject_label,
                    'scope_type' => $entry->organization_id === null ? null : $organizationType,
                    'scope_id' => $entry->organization_id,
                    'surface' => $entry->surface,
                    'ip' => $entry->ip,
                    'user_agent' => $entry->user_agent,
                    'changes' => $this->json($entry->changes),
                    'context' => $context,
                    'created_at' => Carbon::parse($entry->created_at),
                ]);

                $imported++;
            }
        });

        $this->components->info("Imported {$imported} Roster audit entries.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
