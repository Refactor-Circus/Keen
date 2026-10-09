<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Data;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RefactorCircus\Foundation\Packages\PackageRegistry;
use RefactorCircus\Keen\Domains\Audit\Actions\RecordAuditEventAction;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * Builds one of the application's own audit entries:
 *
 *     Keen::record('invoice.paid')->on($invoice)->in($organization)->with(['amount' => 100])->save();
 */
final class PendingAuditEntry
{
    private ?Model $subject = null;

    private ?Model $scope = null;

    private ?Model $actor = null;

    private string $source = AuditEntryModel::SOURCE_APP;

    /** @var array<string, mixed> */
    private array $context = [];

    /** @var array<string, array{0: mixed, 1: mixed}> */
    private array $changes = [];

    public function __construct(private readonly string $action) {}

    /**
     * What the event happened to.
     */
    public function on(Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * What the event is scoped to - an organization, a tenant - so it can be
     * read per scope.
     */
    public function in(?Model $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Record the entry as a package of the suite rather than the application,
     * for a package's own events, so they show in its history.
     *
     * @throws InvalidArgumentException when no package is registered under the key
     */
    public function source(string $package): self
    {
        if (! app(PackageRegistry::class)->has($package)) {
            throw new InvalidArgumentException("No package is registered under the key [{$package}].");
        }

        $this->source = $package;

        return $this;
    }

    /**
     * Who did it. Defaults to the signed-in user.
     */
    public function by(?Model $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    /**
     * Extra details to keep with the entry.
     *
     * @param  array<string, mixed>  $context
     */
    public function with(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Field changes, as `field => [old, new]`.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     */
    public function changes(array $changes): self
    {
        $this->changes = array_merge($this->changes, $changes);

        return $this;
    }

    public function save(): AuditEntryModel
    {
        $data = ['action' => $this->action, 'context' => $this->context, 'changes' => $this->changes];

        validator($data, RecordAuditEventAction::rules())->validate();

        return app(RecordAuditEventAction::class)->execute($data, $this->actor, $this->subject, $this->scope, $this->source);
    }
}
