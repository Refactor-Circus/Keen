<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\RecordAuditEventTool;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ShowAuditEntryTool;
use RefactorCircus\Keen\Domains\Audit\Services\KeenAuditTrail;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductCreatedActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditFilter;

it('lists, shows and records entries over the json api', function (): void {
    $entry = Keen::record('invoice.paid')->with(['amount' => 1])->save();

    $this->getJson('/api/keen/entries?source=app')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'invoice.paid');

    $this->getJson("/api/keen/entries/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('data.context.amount', 1)
        ->assertJsonPath('data.hash', $entry->hash);

    $this->postJson('/api/keen/entries', ['action' => 'invoice.void', 'subject_label' => 'INV-1'])
        ->assertCreated()
        ->assertJsonPath('data.source', 'app')
        ->assertJsonPath('data.subject.label', 'INV-1');

    $this->postJson('/api/keen/entries', ['action' => 'Not Dotted'])->assertUnprocessable();

    $this->getJson('/api/keen/entries/999')->assertNotFound()->assertExactJson(['message' => 'Not found.']);
});

it('pages the mcp listing by cursor', function (): void {
    Keen::record('invoice.paid')->save();
    Keen::record('invoice.sent')->save();

    $first = mcpTool(ListAuditEntriesTool::class, ['per_page' => 1])->assertOk()->assertSee('invoice.sent');
    /** @var array{result: array{structuredContent: array{meta: array{next_cursor: string}}}} $payload */
    $payload = (fn (): array => $this->response->toArray())->call($first);

    mcpTool(ListAuditEntriesTool::class, ['per_page' => 1, 'cursor' => $payload['result']['structuredContent']['meta']['next_cursor']])
        ->assertOk()
        ->assertSee('invoice.paid')
        ->assertDontSee('invoice.sent');
});

it('offers the same over mcp', function (): void {
    $entry = Keen::record('invoice.paid')->save();

    mcpTool(ListAuditEntriesTool::class, ['action' => 'invoice.'])->assertOk()->assertSee('invoice.paid');
    mcpTool(ShowAuditEntryTool::class, ['id' => $entry->id])->assertOk()->assertSee($entry->hash);
    mcpTool(RecordAuditEventTool::class, ['action' => 'invoice.void', 'changes' => ['status' => ['open', 'void']]])->assertOk()->assertSee('invoice.void');
});

it('lets people who may not read the whole log read their own entries', function (): void {
    config()->set('keen.authorization', true);
    Gate::define('viewAuditLog', fn (User $user): bool => false);
    $ada = user();
    $grace = user('Grace Hopper');

    Keen::record('note.mine')->by($ada)->save();
    $theirs = Keen::record('note.theirs')->by($grace)->save();

    $this->actingAs($ada)->getJson('/api/keen/entries')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'note.mine')
        ->assertJsonMissingPath('data.0.ip');

    $this->actingAs($ada)->getJson("/api/keen/entries/{$theirs->id}")->assertForbidden();
});

it('answers guests 401 once authorization is on', function (): void {
    config()->set('keen.authorization', true);

    $this->getJson('/api/keen/entries')->assertUnauthorized();
});

it('serves every package\'s history once installed', function (): void {
    expect(app(AuditTrail::class))->toBeInstanceOf(KeenAuditTrail::class);

    $product = ProductModel::query()->create(['name' => 'Cog']);
    ProductCreatedActionEvent::dispatch($product);
    Keen::record('invoice.paid')->save();

    $this->getJson('/shop/history?subject_type='.urlencode(ProductModel::class).'&subject_id='.$product->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'product.created')
        ->assertJsonPath('data.0.source', 'shop');
});

it('gives the audit log its own section in atrium', function (): void {
    ValidateCsrfToken::except(['*']);
    app()->detectEnvironment(fn (): string => 'local');
    $this->actingAs(user());

    $entry = Keen::record('invoice.paid')->with(['amount' => 7])->changes(['status' => ['open', 'paid']])->save();

    $this->get('/atrium/keen/entries')
        ->assertOk()
        ->assertSee('Audit log')
        ->assertSee('invoice.paid')
        ->assertSee('data-testid="record-note-card"', false);

    $this->get("/atrium/keen/entries/{$entry->id}")
        ->assertOk()
        ->assertSee($entry->hash)
        ->assertSee('data-testid="audit-changes"', false)
        ->assertSee('&quot;amount&quot;: 7', false);

    $this->post('/atrium/keen/entries', ['action' => 'note.added', 'context' => ['note' => 'Checked']])
        ->assertRedirect('/atrium/keen/entries');

    $this->get('/atrium/keen/entries')->assertSee('note.added');
});

it('shows each package\'s history in its own atrium screens', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog']);
    ProductCreatedActionEvent::dispatch($product);

    $html = Blade::render('<x-keen::audit-trail source="shop" :subject="$product" />', ['product' => $product]);

    expect($html)->toContain('product.created')->toContain('/atrium/keen/entries/');
});

it('reads the trail by scope', function (): void {
    $org = ProductModel::query()->create(['name' => 'Acme']);
    Keen::record('note.in')->in($org)->save();
    Keen::record('note.out')->save();

    $page = app(AuditTrail::class)->entries(AuditFilter::make()->scope($org));

    expect(array_map(fn ($entry) => $entry->action, $page->entries))->toBe(['note.in']);
});
