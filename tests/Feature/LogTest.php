<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RefactorCircus\Keen\Domains\Audit\Exceptions\AuditLogIsAppendOnlyException;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Services\AuditLog;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

it('chains every entry to the one before it', function (): void {
    $first = Keen::record('invoice.paid')->save();
    $second = Keen::record('invoice.refunded')->save();

    expect($first->source)->toBe('app')
        ->and($second->previous_hash)->toBe($first->hash)
        ->and(app(AuditLog::class)->verify())->toBeNull();

    $this->artisan('keen:verify')->assertSuccessful();
});

it('finds an entry altered after it was written', function (): void {
    Keen::record('invoice.paid')->save();
    $tampered = Keen::record('invoice.refunded')->with(['amount' => 10])->save();

    DB::table('keen_entries')->where('id', $tampered->id)->update(['context' => json_encode(['amount' => 1])]);

    expect(app(AuditLog::class)->verify())->toBe($tampered->id);

    $this->artisan('keen:verify')->assertFailed();
});

it('refuses to change or delete an entry', function (): void {
    $entry = Keen::record('invoice.paid')->save();

    expect(fn () => $entry->update(['action' => 'invoice.void']))->toThrow(AuditLogIsAppendOnlyException::class)
        ->and(fn () => $entry->delete())->toThrow(AuditLogIsAppendOnlyException::class);
});

it('records the application\'s own events with their subject, scope and actor', function (): void {
    $invoice = ProductModel::query()->create(['name' => 'INV-1']);
    $org = ProductModel::query()->create(['name' => 'Acme']);
    $ada = user();

    $entry = Keen::record('invoice.paid')->on($invoice)->in($org)->by($ada)
        ->with(['amount' => 100, 'token' => 'x'])->changes(['status' => ['open', 'paid']])->save();

    expect($entry->subject_label)->toBe('INV-1')
        ->and($entry->scope_id)->toBe((string) $org->id)
        ->and($entry->actor_label)->toBe('Ada Lovelace')
        ->and($entry->context)->toBe(['amount' => 100, 'token' => '[redacted]'])
        ->and($entry->changes)->toBe(['status' => ['open', 'paid']]);
});

it('prunes entries older than the retention period', function (): void {
    app(AuditLog::class)->append(['source' => 'app', 'action' => 'old.thing', 'surface' => 'cli', 'created_at' => now()->subDays(400)]);
    Keen::record('new.thing')->save();

    $this->artisan('keen:prune')->assertSuccessful();

    expect(AuditEntryModel::query()->pluck('action')->all())->toBe(['new.thing']);

    config()->set('keen.retention_days', null);
    $this->artisan('keen:prune')->expectsOutputToContain('unlimited')->assertSuccessful();
});

it('imports the audit log roster kept before keen', function (): void {
    DB::statement('create table roster_audit_entries (id integer primary key autoincrement, source varchar, action varchar, actor_id varchar null, subject_type varchar null, subject_id varchar null, subject_label varchar null, organization_id varchar null, surface varchar, ip varchar null, user_agent varchar null, changes text null, context text null, previous_hash varchar null, hash varchar, created_at datetime)');
    DB::table('roster_audit_entries')->insert([
        ['source' => 'roster', 'action' => 'user.suspended', 'actor_id' => '1', 'subject_type' => 'user', 'subject_id' => '2', 'subject_label' => 'Bob', 'organization_id' => '01ORG', 'surface' => 'atrium', 'changes' => json_encode(['status' => ['active', 'suspended']]), 'context' => null, 'hash' => 'x', 'created_at' => '2026-01-01 10:00:00'],
        ['source' => 'app', 'action' => 'invoice.paid', 'actor_id' => null, 'subject_type' => null, 'subject_id' => null, 'subject_label' => null, 'organization_id' => null, 'surface' => 'code', 'changes' => null, 'context' => json_encode(['amount' => 5]), 'hash' => 'y', 'created_at' => '2026-01-02 10:00:00'],
    ]);

    $this->artisan('keen:import-roster')->expectsOutputToContain('Imported 2')->assertSuccessful();

    $entries = AuditEntryModel::query()->orderBy('id')->get();

    expect($entries->pluck('source')->all())->toBe(['roster', 'app'])
        ->and($entries[0]->scope_id)->toBe('01ORG')
        ->and($entries[0]->changes)->toBe(['status' => ['active', 'suspended']])
        ->and($entries[0]->context)->toMatchArray(['imported_from' => 'roster'])
        ->and($entries[0]->created_at->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and(app(AuditLog::class)->verify())->toBeNull();

    $this->artisan('keen:import-roster')->assertFailed();
});

it('records a package\'s own event under its source', function (): void {
    expect(Keen::record('cart.noted')->source('shop')->save()->source)->toBe('shop')
        ->and(fn () => Keen::record('cart.noted')->source('nope'))->toThrow(InvalidArgumentException::class);
});
