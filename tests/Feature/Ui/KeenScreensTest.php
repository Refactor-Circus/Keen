<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Data\TabDefinition;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;

/*
 * Keen's own dashboard pages declare themselves as screens (criterion 13):
 * each renders a registered tab and the sections of the slots it declares,
 * and none of the slots it does not.
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'local');
    View::addNamespace('keen-probes', dirname(__DIR__, 2).'/Fixtures/Screens');

    $this->actingAs(user());

    // Three shop entries, so each list screen has three rows.
    $this->entries = array_map(
        fn (int $i): AuditEntryModel => Keen::record('product.step'.$i)->source('shop')->changes(['step' => [$i - 1, $i]])->save(),
        range(1, 3),
    );
});

/**
 * Register a public probe tab on the screen, and a public probe section in
 * each of the slots.
 *
 * @param  list<string>  $slots
 */
function keenScreensRegister(string $screen, array $slots): void
{
    Atrium::screens()->tab($screen, TabDefinition::make('test.probe-tab')->label('Probe tab')->view('keen-probes::ProbeSection')->public()
        ->resolve(fn (): array => ['probe' => 'tab']));

    foreach ($slots as $slot) {
        Atrium::screens()->section($screen, $slot, SectionDefinition::make('test.probe-'.$slot)->view('keen-probes::ProbeSection')->public()
            ->resolve(fn (): array => ['probe' => $slot]));
    }
}

/**
 * Each probe in the markup, in order, with the record it received.
 *
 * @return list<array{probe: string, record: string, class: string}>
 */
function keenScreensProbes(string $html): array
{
    preg_match_all('/data-testid="probe-([^"]+)" data-record="([^"]*)" data-record-class="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => ['probe' => $match[1], 'record' => $match[2], 'class' => $match[3]], $matches);
}

dataset('keen screens', [
    'the entries index' => [
        'keen.entries',
        fn (AuditEntryModel $entry): string => route('atrium.keen.entries.index'),
        'data-testid="audit-entries"',
        ['header.actions', 'before', 'after'],
        true,
        ['aside', 'history'],
    ],
    'an entry' => [
        'keen.entry',
        fn (AuditEntryModel $entry): string => route('atrium.keen.entries.show', $entry->id),
        'data-testid="audit-details"',
        ['header.actions', 'before', 'aside', 'after'],
        false,
        ['row.actions', 'history'],
    ],
    'a package history' => [
        'keen.history',
        fn (AuditEntryModel $entry): string => route('atrium.keen.history.show', 'shop'),
        'data-testid="audit-entries"',
        ['header.actions', 'before', 'after'],
        true,
        ['aside', 'history'],
    ],
]);

it('renders each declared slot\'s probe once, in its place, and none for an undeclared slot', function (string $screen, Closure $url, string $content, array $slots, bool $rows, array $undeclared): void {
    keenScreensRegister($screen, [...$slots, ...($rows ? ['row.actions'] : []), ...$undeclared]);

    $response = $this->get($url($this->entries[0]))->assertOk();
    $html = (string) $response->getContent();
    $probes = array_count_values(array_column(keenScreensProbes($html), 'probe'));

    foreach ($slots as $slot) {
        expect($probes[$slot] ?? 0)->toBe(1, "probe-{$slot} on {$screen}");
    }

    foreach ($undeclared as $slot) {
        expect($probes[$slot] ?? 0)->toBe(0, "probe-{$slot} on {$screen}");
    }

    // Header actions, the tab bar, then the page with `before` above it, its
    // rows' actions in it, `aside` beside it and `after` below it.
    $response->assertSeeInOrder([
        'data-testid="probe-header.actions"',
        'data-testid="screen-tabs"',
        'data-testid="probe-before"',
        $content,
        ...($rows ? ['data-testid="probe-row.actions"'] : []),
        ...(in_array('aside', $slots, true) ? ['data-testid="probe-aside"'] : []),
        'data-testid="probe-after"',
    ], false);
})->with('keen screens');

it('hands the screen\'s record to its sections', function (): void {
    keenScreensRegister('keen.entry', ['header.actions', 'before', 'aside', 'after']);

    $entry = $this->entries[1];
    $probes = keenScreensProbes((string) $this->get(route('atrium.keen.entries.show', $entry->id))->assertOk()->getContent());

    expect($probes)->toHaveCount(4)
        ->and(array_unique(array_column($probes, 'record')))->toBe([(string) $entry->id])
        ->and(array_unique(array_column($probes, 'class')))->toBe([AuditEntryModel::class]);
});

it('renders row.actions once per row, with that row\'s entry as the record', function (string $screen, Closure $url): void {
    keenScreensRegister($screen, ['row.actions']);

    $html = (string) $this->get($url($this->entries[0]))->assertOk()->getContent();
    $rows = array_values(array_filter(keenScreensProbes($html), fn (array $probe): bool => $probe['probe'] === 'row.actions'));

    // Newest first, as the page lists them.
    $ids = array_map(fn (AuditEntryModel $entry): string => (string) $entry->id, array_reverse($this->entries));

    expect($rows)->toHaveCount(count($this->entries))
        ->and(array_column($rows, 'record'))->toBe($ids)
        ->and(array_unique(array_column($rows, 'class')))->toBe([AuditEntryModel::class]);

    // Each row's probe sits in that row, after the entry's own link.
    foreach (array_reverse($this->entries) as $entry) {
        expect($html)->toMatch('/'.preg_quote('href="'.route('atrium.keen.entries.show', $entry->id).'"', '/').'(?:(?!<\/tr>).)*data-testid="probe-row\.actions" data-record="'.$entry->id.'"/s');
    }
})->with([
    'the entries index' => ['keen.entries', fn (AuditEntryModel $entry): string => route('atrium.keen.entries.index')],
    'a package history' => ['keen.history', fn (AuditEntryModel $entry): string => route('atrium.keen.history.show', 'shop')],
]);

it('shows the registered tab and opens it through ?tab= in place of the page', function (string $screen, Closure $url, string $content, array $slots, bool $rows, array $undeclared): void {
    keenScreensRegister($screen, [...$slots, ...($rows ? ['row.actions'] : [])]);

    $page = $url($this->entries[0]);

    $this->get($page)
        ->assertOk()
        ->assertSee('data-testid="tab-overview"', false)
        ->assertSee('data-testid="tab-test.probe-tab"', false)
        ->assertDontSee('data-testid="tab-panel-test.probe-tab"', false);

    // A fresh request, so nothing is remembered from the one above.
    app()->forgetScopedInstances();

    $open = $this->get($page.(str_contains($page, '?') ? '&' : '?').'tab=test.probe-tab')
        ->assertOk()
        ->assertSee('data-testid="tab-panel-test.probe-tab"', false)
        ->assertDontSee($content, false);

    $probes = array_column(keenScreensProbes((string) $open->getContent()), 'probe');

    // Only the header actions and the tab's own panel.
    expect($probes)->toBe(['header.actions', 'tab']);
})->with('keen screens');
