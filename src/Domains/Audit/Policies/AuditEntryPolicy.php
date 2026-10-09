<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Foundation\Audit\History;
use RefactorCircus\Foundation\Policies\Policy;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * The bundled audit log policy.
 *
 * Reading defers to a `viewAuditLog` Gate ability (given the entry's source)
 * and recording to `recordAuditEvent`, when the application defines them;
 * without them anyone signed in may. People may always read entries they made
 * or that are about them. Point `keen.policies` at another class to change it.
 */
class AuditEntryPolicy extends Policy
{
    public const string RECORD_ABILITY = 'recordAuditEvent';

    public function viewAny(Model $user): bool
    {
        return $this->defers($user, History::ABILITY, [null]);
    }

    public function view(Model $user, AuditEntryModel $entry): bool
    {
        return $entry->concerns($user) || $this->defers($user, History::ABILITY, [$entry->source]);
    }

    /**
     * IP addresses and user agents are personal data: only people who may
     * read the whole log see them.
     */
    public function viewPersonalData(Model $user, AuditEntryModel $entry): bool
    {
        return $this->defers($user, History::ABILITY, [$entry->source]);
    }

    public function create(Model $user): bool
    {
        return $this->defers($user, self::RECORD_ABILITY, []);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function defers(Model $user, string $ability, array $arguments): bool
    {
        return ! Gate::has($ability) || Gate::forUser($user)->allows($ability, $arguments);
    }
}
