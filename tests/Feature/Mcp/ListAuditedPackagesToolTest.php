<?php

declare(strict_types=1);

use RefactorCircus\Keen\Domains\Audit\Mcp\Tools\ListAuditedPackagesTool;
use RefactorCircus\Keen\Mcp\KeenServer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

it('answers the audited packages as structured content within its output schema', function (): void {
    app(PackageRegistry::class)->register(Package::make('warehouse', 'RefactorCircus\\Keen\\Tests\\Fixtures\\Warehouse')->label('Alpha'));

    // mcpTool() fails the test when the answer leaves the tool's output schema.
    $response = mcpTool(ListAuditedPackagesTool::class)->assertOk();

    /** @var array{result: array{structuredContent: array<string, mixed>}} $payload */
    $payload = (fn (): array => $this->response->toArray())->call($response);

    expect($payload['result']['structuredContent'])->toBe(['data' => [
        ['key' => 'warehouse', 'label' => 'Alpha'],
        ['key' => 'shop', 'label' => 'Shop'],
    ]]);
});

it('is offered on the Keen server', function (): void {
    expect(KeenServer::TOOLS)->toContain(ListAuditedPackagesTool::class);
});
