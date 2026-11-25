<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

it('lists the audited packages by key and label', function (): void {
    app(PackageRegistry::class)->register(Package::make('warehouse', 'RefactorCircus\\Keen\\Tests\\Fixtures\\Warehouse')->label('Alpha'));

    $this->getJson(route('keen.packages.index'))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['key' => 'warehouse', 'label' => 'Alpha'],
            ['key' => 'shop', 'label' => 'Shop'],
        ]]);
});

it('answers guests 401 once authorization is on', function (): void {
    config()->set('keen.authorization', true);

    $this->getJson(route('keen.packages.index'))->assertUnauthorized();
});
