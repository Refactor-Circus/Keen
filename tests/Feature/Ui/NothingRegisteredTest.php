<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;

/*
 * With nothing registered, Keen's screens render the markup they rendered
 * before they declared themselves (D-032): no tab bar, no section wrapper,
 * and the same table columns. No snapshot: structure only.
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    // Nothing at all: not even Keen's own History section and Activity tab.
    Atrium::screens()->flush();

    $this->actingAs(user());

    $this->entry = Keen::record('product.renamed')->source('shop')->changes(['name' => ['Cog', 'Gear']])->save();
    Keen::record('product.created')->source('shop')->save();
});

dataset('unextended keen screens', [
    // ui/index's entries table: when, action, record, by and through.
    'the entries index' => [fn (AuditEntryModel $entry): string => route('atrium.keen.entries.index'), 5],
    // ui/show's changes table: field, before and after.
    'an entry' => [fn (AuditEntryModel $entry): string => route('atrium.keen.entries.show', $entry->id), 3],
    // Atrium's history/show entries table, as ported: when, action, record, by and through.
    'a package history' => [fn (AuditEntryModel $entry): string => route('atrium.keen.history.show', 'shop'), 5],
]);

it('renders no tab bar, no section and no extra column', function (Closure $url, int $headerCells): void {
    $html = (string) $this->get($url($this->entry))->assertOk()->getContent();

    expect($html)
        ->not->toContain('data-testid="screen-tabs"')
        ->not->toContain('data-testid="tab-')
        ->not->toMatch('/data-testid="section-/')
        ->and(substr_count($html, '<th '))->toBe($headerCells);
})->with('unextended keen screens');
