<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\RecordAuditEventTool;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Domains\Audit\Policies\AuditEntryPolicy;
use RefactorCircus\Keen\KeenServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Policies\ReadOnlyAuditPolicy;

it('registers the policies from the config', function (): void {
    expect(Gate::getPolicyFor(AuditEntryModel::class))->toBeInstanceOf(AuditEntryPolicy::class);
});

it('uses a policy swapped in the config', function (): void {
    config()->set('keen.authorization', true);
    config()->set('keen.policies.'.AuditEntryModel::class, ReadOnlyAuditPolicy::class);

    // Register the policies again, as the provider does on boot.
    (fn () => $this->registerPolicies())->call(app()->getProvider(KeenServiceProvider::class));

    $this->actingAs(user());

    expect(Gate::getPolicyFor(AuditEntryModel::class))->toBeInstanceOf(ReadOnlyAuditPolicy::class);

    $this->getJson('/api/keen/entries')->assertOk();
    $this->postJson('/api/keen/entries', ['action' => 'invoice.void'])->assertForbidden();
    mcpTool(RecordAuditEventTool::class, ['action' => 'invoice.void'])->assertHasErrors();

    expect(AuditEntryModel::query()->count())->toBe(0);
});
