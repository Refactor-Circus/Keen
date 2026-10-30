<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Atrium\Domains\Navigation\Data\NavItem;
use RefactorCircus\Atrium\Support\Icons;
use RefactorCircus\Keen\Atrium\KeenPlugin;
use RefactorCircus\Keen\Support\Tables;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/*
 * The "History" nav items Keen adds for every auditing package (criterion 8).
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    $this->actingAs(user());
});

/**
 * The dashboard's markup.
 */
function historyNavPage(): string
{
    return (string) test()->get('/atrium')->assertOk()->getContent();
}

/**
 * The docked panel's markup for one nav group, or null when it is not there.
 */
function historyNavGroup(string $html, string $label): ?string
{
    $start = strpos($html, 'data-testid="nav-panel-section" data-section="group-'.str($label)->slug().'"');

    if ($start === false) {
        return null;
    }

    $end = strpos($html, '</ul>', $start);

    return substr($html, $start, $end === false ? null : $end - $start);
}

/**
 * The href of a package's history page.
 */
function historyNavHref(string $package): string
{
    return 'href="'.route('atrium.keen.history.show', $package).'"';
}

it('links each auditing package\'s history under its own group', function (): void {
    $group = historyNavGroup(historyNavPage(), 'Shop');

    expect($group)->not->toBeNull()
        ->toContain('>Shop</p>')
        ->toContain(historyNavHref('shop'))
        ->toMatch('/<span class="flex-1 truncate[^"]*">History<\/span>/');
});

it('keeps keen\'s own audit log item', function (): void {
    expect(historyNavPage())->toContain('href="'.route('atrium.keen.entries.index').'"')
        ->toContain('Audit log');
});

it('leaves out a package that does not audit', function (): void {
    config()->set('shop.audit.enabled', false);

    expect(historyNavPage())->not->toContain(historyNavHref('shop'));
});

it('leaves out a package whose history rule refuses the viewer', function (): void {
    app(PackageRegistry::class)->get('shop')->authorizeHistory(fn (): bool => false);

    expect(historyNavPage())->not->toContain(historyNavHref('shop'));
});

it('groups an item under the configured group', function (): void {
    config()->set('keen.atrium.history.nav.groups.shop', 'Storefront');

    $html = historyNavPage();

    expect(historyNavGroup($html, 'Storefront'))->not->toBeNull()
        ->toContain('>Storefront</p>')
        ->toContain(historyNavHref('shop'))
        ->and(historyNavGroup($html, 'Shop'))->toBeNull();
});

it('ships group defaults for showroom and pennantplus only', function (): void {
    /** @var array{atrium: array{history: array{nav: array<string, mixed>}}} $config */
    $config = require dirname(__DIR__, 3).'/config/keen.php';

    expect($config['atrium']['history']['nav'])->toMatchArray([
        'enabled' => true,
        'sort' => 90,
        'exclude' => [],
    ])->and($config['atrium']['history']['nav']['groups'])->toBe([
        'showroom' => 'showroom::showroom.catalog',
        'pennantplus' => 'pennantplus::pennantplus.group',
    ]);
});

it('leaves out an excluded package', function (): void {
    config()->set('keen.atrium.history.nav.exclude', ['shop']);

    expect(historyNavPage())->not->toContain(historyNavHref('shop'))
        ->toContain(historyNavHref('atrium'));
});

it('adds no history items while they are turned off', function (): void {
    config()->set('keen.atrium.history.nav.enabled', false);

    expect(historyNavPage())->not->toContain(historyNavHref('shop'))
        ->not->toContain(historyNavHref('atrium'))
        ->toContain('href="'.route('atrium.keen.entries.index').'"');
});

it('adds no history item for keen itself', function (): void {
    expect(historyNavPage())->not->toContain(historyNavHref('keen'))
        ->toContain(historyNavHref('shop'));
});

it('checks the history items without querying the audit log, however many packages audit', function (): void {
    /** @var list<string> $queries */
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $register = function (int $from, int $to): void {
        foreach (range($from, $to) as $i) {
            app(PackageRegistry::class)->register(Package::make('audited'.$i, 'Vendor\\Audited'.$i)->label('Audited '.$i));
        }
    };

    $audit = function () use (&$queries): int {
        return count(array_filter($queries, fn (string $sql): bool => str_contains($sql, Tables::name('entries'))));
    };

    // A first visit sets the viewer up; measure the ones after it.
    $register(1, 2);
    historyNavPage();

    $queries = [];
    $few = historyNavPage();
    $fewAudit = $audit();
    $fewAll = count($queries);

    $register(3, 8);

    $queries = [];
    $many = historyNavPage();
    $manyAudit = $audit();
    $manyAll = count($queries);

    // The bound: no audit log query at all, with two packages or eight.
    expect($few)->toContain(historyNavHref('audited2'))
        ->and($many)->toContain(historyNavHref('audited8'))
        ->and($fewAudit)->toBe(0)
        ->and($manyAudit)->toBe(0)
        ->and($manyAll)->toBe($fewAll);
});

it('sorts the history items by the configured sort, 90 by default', function (): void {
    $sorts = fn (): array => array_values(array_map(
        fn (NavItem $item): int => $item->sort,
        array_filter((new KeenPlugin)->navigation(), fn (NavItem $item): bool => $item->route === 'atrium.keen.history.show'),
    ));

    expect($sorts())->not->toBeEmpty()->each->toBe(90);

    config()->set('keen.atrium.history.nav.sort', 5);

    expect($sorts())->each->toBe(5);
});

it('labels and groups the items, and decides who sees them only when they render', function (): void {
    $asked = 0;
    app(PackageRegistry::class)->get('shop')->authorizeHistory(function () use (&$asked): bool {
        $asked++;

        return true;
    });

    $items = array_values(array_filter((new KeenPlugin)->navigation(), fn (NavItem $item): bool => $item->route === 'atrium.keen.history.show'));
    $shop = array_values(array_filter($items, fn (NavItem $item): bool => $item->group === 'Shop'));

    expect($asked)->toBe(0)
        ->and($shop)->toHaveCount(1)
        ->and($shop[0]->label)->toBe('History')
        ->and($shop[0]->resolveUrl())->toBe(route('atrium.keen.history.show', 'shop'))
        ->and($shop[0]->icon)->toBe(Icons::svg('clipboard-document-list'));

    historyNavPage();

    expect($asked)->toBeGreaterThan(0);
});
