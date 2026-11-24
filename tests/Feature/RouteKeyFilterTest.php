<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use RefactorCircus\Keen\Domains\Audit\Actions\ListAuditEntriesAction;
use RefactorCircus\Keen\Domains\Audit\Data\ListAuditEntriesData;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Host\OrganizationModel;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/*
 * Records named the way Atrium's `keen.history` section and `keen.activity`
 * tab name them on another package's screen (D-019): `subject_type` /
 * `scope_type` is the morph type Keen stores, and the id is the record the
 * screen passes, its route key (an organization's slug). Entries store the
 * key, a ULID here, so a filter on the slug as given found nothing on a real
 * host.
 */

beforeEach(function (): void {
    $this->org = OrganizationModel::query()->create(['name' => 'Acme', 'slug' => 'w78504']);
    $this->product = ProductModel::query()->create(['name' => 'Cog']);
    $other = OrganizationModel::query()->create(['name' => 'Other', 'slug' => 'other']);

    Keen::record('member.invited')->on($this->org)->in($this->org)->save();
    Keen::record('product.priced')->on($this->product)->in($this->org)->save();
    Keen::record('member.left')->on($other)->in($other)->save();
});

/**
 * @param  array<string, mixed>  $filters
 * @return array<int, string>
 */
function routeKeyActions(array $filters): array
{
    $page = app(ListAuditEntriesAction::class)->execute(ListAuditEntriesData::from($filters));

    return array_map(fn (AuditEntryModel $entry): string => $entry->action, $page->items());
}

it('filters by a subject or scope named by its route key, matching the key entries store', function (): void {
    expect(AuditEntryModel::query()->where('scope_id', $this->org->id)->count())->toBe(2)
        ->and(routeKeyActions(['scope_type' => OrganizationModel::class, 'scope_id' => 'w78504']))->toBe(['product.priced', 'member.invited'])
        ->and(routeKeyActions(['subject_type' => OrganizationModel::class, 'subject_id' => 'w78504']))->toBe(['member.invited'])
        ->and(routeKeyActions(['subject_type' => ProductModel::class, 'subject_id' => $this->product->slug]))->toBe(['product.priced']);
});

it('still filters by the key', function (): void {
    expect(routeKeyActions(['scope_type' => OrganizationModel::class, 'scope_id' => $this->org->id]))->toBe(['product.priced', 'member.invited'])
        ->and(routeKeyActions(['subject_type' => ProductModel::class, 'subject_id' => (string) $this->product->id]))->toBe(['product.priced']);
});

it('resolves a morph alias through the morph map', function (): void {
    Relation::morphMap(['organization' => OrganizationModel::class]);

    try {
        Keen::record('member.promoted')->on($this->org)->save();

        expect(routeKeyActions(['subject_type' => 'organization', 'subject_id' => 'w78504']))->toBe(['member.promoted']);
    } finally {
        Relation::morphMap([], false);
    }
});

it('matches an unknown type or record as given, as before', function (): void {
    $this->postJson('/api/keen/entries', ['action' => 'invoice.paid', 'subject_type' => 'invoice', 'subject_id' => 'INV-1'])->assertCreated();

    expect(routeKeyActions(['subject_type' => 'invoice', 'subject_id' => 'INV-1']))->toBe(['invoice.paid'])
        ->and(routeKeyActions(['scope_type' => OrganizationModel::class, 'scope_id' => 'nobody']))->toBe([])
        ->and(routeKeyActions(['scope_id' => 'w78504']))->toBe([]);
});

it('answers the history section and activity tab queries over the json api', function (): void {
    $this->getJson('/api/keen/entries?'.http_build_query(['source' => 'app', 'scope_type' => OrganizationModel::class, 'scope_id' => 'w78504']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.scope.id', $this->org->id);

    $this->getJson('/api/keen/entries?'.http_build_query(['source' => 'app', 'subject_type' => OrganizationModel::class, 'subject_id' => 'w78504']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'member.invited')
        ->assertJsonPath('data.0.subject.id', $this->org->id);

    $this->getJson('/api/keen/entries?'.http_build_query(['subject_type' => OrganizationModel::class, 'subject_id' => 'nobody']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('offers the same over mcp', function (): void {
    mcpTool(ListAuditEntriesTool::class, ['scope_type' => OrganizationModel::class, 'scope_id' => 'w78504'])
        ->assertOk()
        ->assertSee('product.priced')
        ->assertSee('member.invited')
        ->assertDontSee('member.left');

    mcpTool(ListAuditEntriesTool::class, ['subject_type' => ProductModel::class, 'subject_id' => $this->product->slug])
        ->assertOk()
        ->assertSee('product.priced')
        ->assertDontSee('member.invited');
});
