<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use JayI\Foundation\Audit\Contracts\AuditTrail;
use JayI\Keen\Domains\Audit\Mcp\Tools\ListAuditEntriesTool;
use JayI\Keen\Domains\Audit\Mcp\Tools\RecordAuditEventTool;
use JayI\Keen\Domains\Audit\Mcp\Tools\ShowAuditEntryTool;
use JayI\Keen\Domains\Audit\Services\KeenAuditTrail;
use JayI\Keen\Facades\Keen;
use JayI\Keen\Mcp\KeenServer;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductCreatedActionEvent;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

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
});

it('offers the same over mcp', function (): void {
    $entry = Keen::record('invoice.paid')->save();

    KeenServer::tool(ListAuditEntriesTool::class, ['action' => 'invoice.'])->assertOk()->assertSee('invoice.paid');
    KeenServer::tool(ShowAuditEntryTool::class, ['id' => $entry->id])->assertOk()->assertSee($entry->hash);
    KeenServer::tool(RecordAuditEventTool::class, ['action' => 'invoice.void'])->assertOk()->assertSee('invoice.void');
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

    $html = Blade::render('<x-atrium::audit-trail source="shop" :subject="$product" />', ['product' => $product]);

    expect($html)->toContain('product.created')->toContain('/atrium/keen/entries/');
});
