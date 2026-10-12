<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RefactorCircus\Keen\Domains\Audit\Exceptions\AuditLogIsAppendOnlyException;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Services\AuditLog;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Actions\UpdateProductAction;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

it('migrates into the renamed tables', function (): void {
    expect(Schema::hasTable('crm_entries'))->toBeTrue()
        ->and(Schema::hasTable('crm_chain'))->toBeTrue()
        ->and(Schema::hasTable('keen_entries'))->toBeFalse()
        ->and(Schema::hasTable('keen_chain'))->toBeFalse()
        ->and((new AuditEntryModel)->getTable())->toBe('crm_entries');
});

it('chains entries through the renamed chain head and finds tampering', function (): void {
    $first = Keen::record('invoice.paid')->save();
    $second = Keen::record('invoice.refunded')->with(['amount' => 10])->save();

    expect($second->previous_hash)->toBe($first->hash)
        ->and(DB::table('crm_chain')->value('head_hash'))->toBe($second->hash)
        ->and(DB::table('crm_entries')->count())->toBe(2)
        ->and(app(AuditLog::class)->verify())->toBeNull();

    $this->artisan('keen:verify')->assertSuccessful();

    DB::table('crm_entries')->where('id', $second->id)->update(['context' => json_encode(['amount' => 1])]);

    expect(app(AuditLog::class)->verify())->toBe($second->id);
    $this->artisan('keen:verify')->assertFailed();
});

it('keeps the renamed log append-only', function (): void {
    $entry = Keen::record('invoice.paid')->save();

    expect(fn () => $entry->update(['action' => 'invoice.void']))->toThrow(AuditLogIsAppendOnlyException::class)
        ->and(fn () => $entry->delete())->toThrow(AuditLogIsAppendOnlyException::class);
});

it('records another package\'s actions and prunes the renamed log', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog', 'price' => 100]);
    app(UpdateProductAction::class)->execute($product, ['name' => 'Sprocket', 'price' => 120]);
    app(AuditLog::class)->append(['source' => 'app', 'action' => 'old.thing', 'surface' => 'cli', 'created_at' => now()->subDays(400)]);

    expect(AuditEntryModel::query()->where('source', 'shop')->count())->toBe(1);

    $this->artisan('keen:prune')->assertSuccessful();

    expect(AuditEntryModel::query()->where('action', 'old.thing')->exists())->toBeFalse()
        ->and(AuditEntryModel::query()->where('source', 'shop')->count())->toBe(1);
});

it('lists, shows and records entries over the json api and mcp', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog', 'price' => 100]);
    app(UpdateProductAction::class)->execute($product, ['name' => 'Sprocket', 'price' => 120]);
    $entry = Keen::record('invoice.paid')->on($product)->save();

    $this->getJson('/api/keen/entries?source=app')->assertOk()->assertJsonPath('data.0.id', $entry->id);
    $this->getJson("/api/keen/entries/{$entry->id}")->assertOk()->assertJsonPath('data.action', 'invoice.paid');
    $this->postJson('/api/keen/entries', ['action' => 'invoice.void'])->assertCreated();
    $this->getJson('/shop/history?subject_type='.urlencode(ProductModel::class).'&subject_id='.$product->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'product.updated');

    mcpTool(ListAuditEntriesTool::class, ['source' => 'app'])->assertOk()->assertSee('invoice.void');

    expect(app(AuditLog::class)->verify())->toBeNull();
});

it('imports roster\'s old log into the renamed tables, once', function (): void {
    DB::statement('create table roster_audit_entries (id integer primary key autoincrement, source varchar, action varchar, actor_id varchar null, subject_type varchar null, subject_id varchar null, subject_label varchar null, organization_id varchar null, surface varchar, ip varchar null, user_agent varchar null, changes text null, context text null, previous_hash varchar null, hash varchar, created_at datetime)');
    DB::table('roster_audit_entries')->insert(['source' => 'roster', 'action' => 'user.suspended', 'surface' => 'atrium', 'hash' => 'x', 'created_at' => '2026-01-01 10:00:00']);

    $this->artisan('keen:import-roster')->expectsOutputToContain('Imported 1')->assertSuccessful();
    $this->artisan('keen:import-roster')->assertFailed();

    expect(DB::table('crm_entries')->count())->toBe(1)
        ->and(app(AuditLog::class)->verify())->toBeNull();
});
