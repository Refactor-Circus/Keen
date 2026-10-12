<?php

declare(strict_types=1);

use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Support\Tables;

it('ships the default table names in its config', function (): void {
    $config = require dirname(__DIR__, 2).'/config/keen.php';

    expect($config['tables'])->toBe(Tables::DEFAULTS);
});

it('keeps the default for tables a published config leaves out', function (): void {
    config()->set('keen.tables', ['entries' => 'crm_entries']);

    expect(Tables::name('entries'))->toBe('crm_entries')
        ->and(Tables::name('chain'))->toBe('keen_chain')
        ->and((new AuditEntryModel)->getTable())->toBe('crm_entries');
});
