<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Support\Tables;
use stdClass;

/**
 * Writes and checks the hash-chained audit log.
 *
 * Each entry's hash covers its content and the previous entry's hash, so
 * editing any stored entry - or removing one from the middle - breaks every
 * link after it.
 */
final class AuditLog
{
    /**
     * The columns every entry is stored with, in order.
     *
     * @var list<string>
     */
    private const array COLUMNS = [
        'source', 'action', 'actor_type', 'actor_id', 'actor_label', 'subject_type', 'subject_id', 'subject_label',
        'scope_type', 'scope_id', 'surface', 'ip', 'user_agent', 'changes', 'context', 'previous_hash', 'hash', 'created_at',
    ];

    /**
     * Append one entry to the chain.
     *
     * Inside a database transaction the entry is appended once it commits,
     * so the chain head is locked only for the append itself, never for the
     * rest of the surrounding transaction. Until then the returned entry is
     * unsaved (`exists` is false; no id or hash yet) and is filled in when it
     * is written; if the transaction rolls back it is never written.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function append(array $attributes): AuditEntryModel
    {
        $entry = $this->prepare($attributes);

        $entry->getConnection()->afterCommit(fn (): AuditEntryModel => $this->write($entry));

        return $entry;
    }

    /**
     * Append many entries, in order, locking the chain head and writing it
     * once rather than once per entry. The rows are inserted in bulk, so the
     * entries' model events do not fire. Like `append()`, inside a database
     * transaction the entries are appended once it commits.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return int How many entries were (or will be, on commit) appended.
     */
    public function appendMany(array $entries): int
    {
        if ($entries === []) {
            return 0;
        }

        $models = array_map($this->prepare(...), array_values($entries));

        $models[0]->getConnection()->afterCommit(function () use ($models): void {
            $this->writeMany($models);
        });

        return count($models);
    }

    /**
     * The id of the first entry that no longer matches the chain, or null
     * when the log is intact. After pruning, the oldest remaining entry
     * anchors the chain.
     */
    public function verify(): ?int
    {
        $expected = false;

        // Plain rows, not models: a full log can be millions of entries.
        foreach (AuditEntryModel::query()->toBase()->lazyById(1000) as $row) {
            /** @var stdClass $row */
            $fields = $this->fieldsFromRow($row);
            $id = is_numeric($row->id) ? (int) $row->id : 0;

            if ($expected !== false && $fields['previous_hash'] !== $expected) {
                return $id;
            }

            $hash = is_string($row->hash) ? $row->hash : '';

            if (! hash_equals($hash, $this->digest($fields))) {
                return $id;
            }

            $expected = $hash;
        }

        return null;
    }

    /**
     * Delete entries older than `$days` days, returning how many went.
     */
    public function prune(int $days): int
    {
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        // In batches, so a large prune never holds one long lock.
        do {
            $ids = AuditEntryModel::query()->toBase()->where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : AuditEntryModel::query()->toBase()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }

    public function hash(AuditEntryModel $entry): string
    {
        return $this->digest([
            'source' => $entry->source,
            'action' => $entry->action,
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'actor_label' => $entry->actor_label,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'subject_label' => $entry->subject_label,
            'scope_type' => $entry->scope_type,
            'scope_id' => $entry->scope_id,
            'surface' => $entry->surface,
            'ip' => $entry->ip,
            'user_agent' => $entry->user_agent,
            'changes' => $entry->changes,
            'context' => $entry->context,
            'previous_hash' => $entry->previous_hash,
            'created_at' => $entry->created_at->getTimestamp(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function prepare(array $attributes): AuditEntryModel
    {
        $attributes['created_at'] ??= Carbon::now()->startOfSecond();

        return new AuditEntryModel($attributes);
    }

    private function write(AuditEntryModel $entry): AuditEntryModel
    {
        return DB::transaction(function () use ($entry): AuditEntryModel {
            $entry->setAttribute('previous_hash', $this->lockHead());
            $entry->setAttribute('hash', $this->hash($entry));
            $entry->save();

            $this->chain()->update(['head_hash' => $entry->hash]);

            return $entry;
        });
    }

    /**
     * @param  list<AuditEntryModel>  $entries
     */
    private function writeMany(array $entries): void
    {
        DB::transaction(function () use ($entries): void {
            $previous = $this->lockHead();
            $rows = [];

            foreach ($entries as $entry) {
                $entry->setAttribute('previous_hash', $previous);
                $entry->setAttribute('hash', $previous = $this->hash($entry));

                $attributes = $entry->getAttributes();
                $rows[] = array_combine(self::COLUMNS, array_map(fn (string $column): mixed => $attributes[$column] ?? null, self::COLUMNS));
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                AuditEntryModel::query()->toBase()->insert($chunk);
            }

            $this->chain()->update(['head_hash' => $previous]);
        });
    }

    /**
     * Lock the chain head, not the newest entry: every writer waits on this
     * one row and then reads the head the last one left.
     */
    private function lockHead(): ?string
    {
        $head = $this->chain()->lockForUpdate()->first();

        if ($head === null) {
            $this->chain()->insertOrIgnore(['id' => 1, 'head_hash' => null]);
            $head = $this->chain()->lockForUpdate()->first();
        }

        $previous = $head?->head_hash;

        return is_string($previous) ? $previous : null;
    }

    /**
     * An entry's hashed fields from its stored row, decoded as the model
     * casts them.
     *
     * @return array<string, mixed>
     */
    private function fieldsFromRow(stdClass $row): array
    {
        $fields = [];

        foreach (self::COLUMNS as $column) {
            $fields[$column] = $row->{$column} ?? null;
        }

        foreach (['changes', 'context'] as $column) {
            $value = $row->{$column} ?? null;
            $fields[$column] = is_string($value) ? json_decode($value, true) : null;
        }

        $fields['created_at'] = Carbon::parse(is_string($row->created_at) ? $row->created_at : '')->getTimestamp();

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function digest(array $fields): string
    {
        $payload = [
            'source' => $fields['source'],
            'action' => $fields['action'],
            'actor_type' => $fields['actor_type'],
            'actor_id' => $this->string($fields['actor_id']),
            'actor_label' => $fields['actor_label'],
            'subject_type' => $fields['subject_type'],
            'subject_id' => $this->string($fields['subject_id']),
            'subject_label' => $fields['subject_label'],
            'scope_type' => $fields['scope_type'],
            'scope_id' => $this->string($fields['scope_id']),
            'surface' => $fields['surface'],
            'ip' => $fields['ip'],
            'user_agent' => $fields['user_agent'],
            'changes' => $this->canonical(is_array($fields['changes']) ? $fields['changes'] : []),
            'context' => $this->canonical(is_array($fields['context']) ? $fields['context'] : []),
            'previous_hash' => $fields['previous_hash'],
            'created_at' => $fields['created_at'],
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function string(mixed $value): ?string
    {
        return $value === null || ! is_scalar($value) ? null : (string) $value;
    }

    private function chain(): Builder
    {
        return DB::table(Tables::name('chain'))->where('id', 1);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->canonical($item) : $item, $value);
    }
}
