<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Events\AuditEntryRetrievedEvent;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Services\AuditLog;
use RefactorCircus\Keen\Domains\Audit\Services\AuditRecorder;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Support\Tables;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductUpdatingActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/**
 * The SQL of every query the callback runs.
 *
 * @return list<string>
 */
function queriesOf(Closure $callback): array
{
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

it('reads a person\'s own entries through two indexed lookups, page by page', function (): void {
    $ada = user();
    $grace = user('Grace Hopper');

    Keen::record('note.by')->by($ada)->save();
    Keen::record('note.other')->by($grace)->save();
    Keen::record('note.about')->by($grace)->on($ada)->save();
    Keen::record('note.again')->by($ada)->on($grace)->save();

    $list = app(ListAuditEntriesAction::class);
    $first = null;
    $sql = queriesOf(function () use ($list, $ada, &$first): void {
        $first = $list->execute(new ListAuditEntriesData(perPage: 2), $ada);
    });
    $second = $list->execute(new ListAuditEntriesData(perPage: 2, cursor: $first?->nextCursor()?->encode()), $ada);

    expect(collect($first?->items())->pluck('action')->all())->toBe(['note.again', 'note.about'])
        ->and(collect($second->items())->pluck('action')->all())->toBe(['note.by'])
        ->and($second->hasMorePages())->toBeFalse()
        ->and(implode(' ', $sql))->toContain('union')->not->toContain(' or ');
});

it('orders a record\'s and a person\'s entries from their indexes', function (): void {
    $indexes = collect(Schema::getIndexes(Tables::name('entries')))->pluck('columns')->all();

    expect($indexes)->toContain(['subject_type', 'subject_id', 'id'], ['actor_type', 'actor_id', 'id'])
        ->not->toContain(['subject_type', 'subject_id'])
        ->not->toContain(['actor_type', 'actor_id']);
});

it('appends an entry recorded inside a transaction once it commits, without locking the chain until then', function (): void {
    $entry = null;
    $inside = [];

    DB::transaction(function () use (&$entry, &$inside): void {
        $inside = queriesOf(function () use (&$entry): void {
            $entry = Keen::record('invoice.paid')->save();
        });

        expect($entry?->exists)->toBeFalse();
    });

    expect(array_filter($inside, fn (string $sql): bool => str_contains($sql, Tables::name('chain'))))->toBeEmpty()
        ->and($entry?->exists)->toBeTrue()
        ->and($entry?->id)->toBeInt()
        ->and(AuditEntryModel::query()->sole()->hash)->toBe($entry?->hash)
        ->and(app(AuditLog::class)->verify())->toBeNull();
});

it('never writes an entry recorded inside a transaction that rolls back', function (): void {
    rescue(fn () => DB::transaction(function (): void {
        Keen::record('invoice.paid')->save();

        throw new RuntimeException('Rolled back.');
    }), report: false);

    expect(AuditEntryModel::query()->count())->toBe(0)
        ->and(DB::table(Tables::name('chain'))->value('head_hash'))->toBeNull();
});

it('appends many entries with one chain lock and one bulk insert', function (): void {
    Keen::record('note.before')->save();

    $sql = queriesOf(fn () => app(AuditLog::class)->appendMany(array_map(
        fn (int $i): array => ['source' => 'app', 'action' => 'bulk.'.$i, 'surface' => 'cli', 'context' => ['i' => $i]],
        range(1, 20),
    )));

    $entries = AuditEntryModel::query()->orderBy('id')->get();
    $touching = fn (string $table): int => count(array_filter($sql, fn (string $query): bool => str_contains($query, '"'.$table.'"')));

    expect($touching(Tables::name('chain')))->toBe(2)
        ->and($touching(Tables::name('entries')))->toBe(1)
        ->and($entries)->toHaveCount(21)
        ->and($entries->last()?->action)->toBe('bulk.20')
        ->and($entries->last()?->context)->toBe(['i' => 20])
        ->and(DB::table(Tables::name('chain'))->value('head_hash'))->toBe($entries->last()?->hash)
        ->and(app(AuditLog::class)->verify())->toBeNull();
});

it('checks the chain without building a model per entry', function (): void {
    foreach (range(1, 3) as $i) {
        Keen::record('note.'.$i)->save();
    }

    $retrieved = 0;
    Event::listen(AuditEntryRetrievedEvent::class, function () use (&$retrieved): void {
        $retrieved++;
    });

    expect(app(AuditLog::class)->verify())->toBeNull()
        ->and($retrieved)->toBe(0);
});

it('asks whether people may see personal data once per source, not once per entry', function (): void {
    config()->set('keen.authorization', true);
    $asked = 0;
    Gate::define('viewAuditLog', function (User $user, ?string $source = null) use (&$asked): bool {
        if ($source !== null) {
            $asked++;
        }

        return true;
    });

    foreach (range(1, 5) as $i) {
        Keen::record('note.'.$i)->save();
    }

    $this->actingAs(user())->getJson('/api/keen/entries')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.ip', null);

    expect($asked)->toBe(1);
});

it('keeps a bounded number of snapshots for actions that never finish', function (): void {
    $recorder = app(AuditRecorder::class);

    foreach (range(1, AuditRecorder::MAX_SNAPSHOTS + 5) as $id) {
        $product = new ProductModel(['name' => 'Cog']);
        $product->id = $id;
        $product->exists = true;

        $recorder->starting(new ProductUpdatingActionEvent($product, []));
    }

    $snapshots = (new ReflectionProperty($recorder, 'snapshots'))->getValue($recorder);

    expect($snapshots)->toBeArray()->toHaveCount(AuditRecorder::MAX_SNAPSHOTS)
        ->and(array_key_first((array) $snapshots))->toBe(ProductModel::class.':6');
});
