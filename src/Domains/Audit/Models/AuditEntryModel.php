<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RefactorCircus\Keen\Domains\Audit\Exceptions\AuditLogIsAppendOnlyException;
use RefactorCircus\Keystone\Audit\Data\AuditEntry;
use RefactorCircus\Keystone\Models\Concerns\DispatchesModelEvents;

/**
 * One entry in the append-only, hash-chained audit log.
 *
 * @property int $id
 * @property string $source
 * @property string $action
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $actor_label
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string|null $scope_type
 * @property string|null $scope_id
 * @property string $surface
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<string, array{0: mixed, 1: mixed}>|null $changes
 * @property array<string, mixed>|null $context
 * @property string|null $previous_hash
 * @property string $hash
 * @property Carbon $created_at
 */
final class AuditEntryModel extends Model
{
    use DispatchesModelEvents;

    /**
     * The source of entries an application records itself.
     */
    public const string SOURCE_APP = 'app';

    public const UPDATED_AT = null;

    protected $table = 'keen_entries';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $refuse = function (self $entry): never {
            throw AuditLogIsAppendOnlyException::forEntry($entry->getKey());
        };

        self::updating($refuse);
        self::deleting($refuse);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries about a model or made by it.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAbout(Builder $query, Model $model): void
    {
        $type = $model->getMorphClass();
        $id = (string) $model->getKey();

        $query->where(fn (Builder $about): Builder => $about
            ->where(fn (Builder $actor): Builder => $actor->where('actor_type', $type)->where('actor_id', $id))
            ->orWhere(fn (Builder $subject): Builder => $subject->where('subject_type', $type)->where('subject_id', $id)));
    }

    /**
     * Whether the entry was made by the model or is about it.
     */
    public function concerns(?Model $model): bool
    {
        if ($model === null) {
            return false;
        }

        $type = $model->getMorphClass();
        $id = (string) $model->getKey();

        return ($this->actor_type === $type && $this->actor_id === $id)
            || ($this->subject_type === $type && $this->subject_id === $id);
    }

    /**
     * The entry as the shared runtime reads it.
     */
    public function toAuditEntry(): AuditEntry
    {
        return new AuditEntry(
            id: $this->id,
            source: $this->source,
            action: $this->action,
            surface: $this->surface,
            createdAt: CarbonImmutable::instance($this->created_at),
            actorId: $this->actor_id,
            actorLabel: $this->actor_label,
            subjectType: $this->subject_type,
            subjectId: $this->subject_id,
            subjectLabel: $this->subject_label,
            changes: $this->changes ?? [],
            context: $this->context ?? [],
        );
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
