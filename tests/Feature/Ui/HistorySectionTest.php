<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Support\Tables;
use RefactorCircus\Keen\Tests\Fixtures\Screens\HostScreensServiceProvider;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductCreatedActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditFilter;
use RefactorCircus\Keystone\Audit\Data\AuditPage;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/*
 * The History section Keen attaches to other packages' screens by wildcard
 * (criteria 10 and 11), on fixture host screens that declare nothing about
 * auditing.
 */

const HISTORY_SECTION = 'data-testid="section-keen.history"';

beforeEach(function (): void {
    app()->register(HostScreensServiceProvider::class);

    $this->actingAs(user());
});

/**
 * A shop product, with the entry its creation recorded.
 *
 * @return array{ProductModel, AuditEntryModel}
 */
function historySectionProduct(string $name): array
{
    $product = ProductModel::query()->create(['name' => $name]);

    ProductCreatedActionEvent::dispatch($product);

    return [$product, AuditEntryModel::query()->where('subject_id', (string) $product->getKey())->sole()];
}

/**
 * The link the trail gives an entry.
 */
function historySectionLink(AuditEntryModel $entry): string
{
    return 'href="'.route('atrium.keen.entries.show', $entry->id).'"';
}

/**
 * Bind an audit trail that counts the pages it is asked for.
 *
 * @return object{calls: int}
 */
function historySectionCountingTrail(): object
{
    $trail = new class implements AuditTrail
    {
        public int $calls = 0;

        public function available(): bool
        {
            return true;
        }

        public function entries(AuditFilter $filter): AuditPage
        {
            $this->calls++;

            return new AuditPage;
        }
    };

    app()->instance(AuditTrail::class, $trail);

    return $trail;
}

/**
 * Count the queries against Keen's audit table while the callback runs.
 */
function historySectionAuditQueries(Closure $callback): int
{
    $count = 0;

    DB::listen(function (QueryExecuted $query) use (&$count): void {
        if (str_contains($query->sql, Tables::name('entries'))) {
            $count++;
        }
    });

    $callback();

    return $count;
}

it('shows a detail screen\'s own history in its after slot, without the record column', function (): void {
    [$cog, $cogEntry] = historySectionProduct('Cog');
    [, $gearEntry] = historySectionProduct('Gear');

    $response = $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))->assertOk();
    $html = (string) $response->getContent();

    $response->assertSeeInOrder(['data-testid="host-after"', HISTORY_SECTION, 'data-testid="audit-trail"', 'data-testid="host-end"'], false);

    expect($html)->toContain(historySectionLink($cogEntry))
        ->not->toContain(historySectionLink($gearEntry))
        // When, action, by and through: no record column.
        ->and(substr_count($html, '<th '))->toBe(4)
        ->and(substr_count($html, HISTORY_SECTION))->toBe(1);
});

it('shows the package\'s whole history on a list screen', function (): void {
    [, $cogEntry] = historySectionProduct('Cog');
    [, $gearEntry] = historySectionProduct('Gear');
    $appEntry = Keen::record('app.thing')->save();

    $response = $this->get(route('keen-test.list', ['screen' => 'shop.products']))->assertOk();
    $html = (string) $response->getContent();

    $response->assertSeeInOrder(['data-testid="host-after"', HISTORY_SECTION, 'data-testid="audit-trail"', 'data-testid="host-end"'], false);

    expect($html)->toContain(historySectionLink($cogEntry))
        ->toContain(historySectionLink($gearEntry))
        ->not->toContain(historySectionLink($appEntry))
        // With the record column.
        ->and(substr_count($html, '<th '))->toBe(5)
        ->and(substr_count($html, HISTORY_SECTION))->toBe(1);
});

it('shows the package\'s whole history on a detail screen without a record', function (): void {
    [, $cogEntry] = historySectionProduct('Cog');
    [, $gearEntry] = historySectionProduct('Gear');

    $html = (string) $this->get(route('keen-test.detail', ['screen' => 'shop.product']))->assertOk()->getContent();

    expect($html)->toContain(HISTORY_SECTION)
        ->toContain(historySectionLink($cogEntry))
        ->toContain(historySectionLink($gearEntry));
});

it('shows nothing on a screen of an unregistered package', function (): void {
    historySectionProduct('Cog');

    $this->get(route('keen-test.detail', ['screen' => 'other.screen']))
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee(HISTORY_SECTION, false);
});

it('shows nothing on a detail screen whose record is not a stored model', function (): void {
    historySectionProduct('Cog');

    $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'unsaved' => 1]))
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee(HISTORY_SECTION, false);
});

it('ships the exclude default of keen\'s and atrium\'s own screens', function (): void {
    /** @var array{atrium: array{history: array<string, mixed>}} $config */
    $config = require dirname(__DIR__, 3).'/config/keen.php';

    expect($config['atrium']['history']['exclude'])->toBe(['keen.*', 'atrium.*'])
        ->and($config['atrium']['history']['screens'])->toBe(['*'])
        ->and($config['atrium']['history']['slot'])->toBe('after')
        ->and($config['atrium']['history']['tabs'])->toBe(['roster.organization' => ['scope' => true]]);
});

it('stays off atrium\'s dashboard and keen\'s own history screen by default, and shows on a package screen', function (): void {
    [$cog] = historySectionProduct('Cog');

    $this->get(route('keen-test.detail', ['screen' => 'atrium.dashboard']))
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee(HISTORY_SECTION, false);

    app()->forgetScopedInstances();
    app()->detectEnvironment(fn (): string => 'local');

    $this->get(route('atrium.keen.history.show', 'shop'))
        ->assertOk()
        ->assertSee('data-testid="audit-entries"', false)
        ->assertDontSee(HISTORY_SECTION, false);

    app()->forgetScopedInstances();

    $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))
        ->assertOk()
        ->assertSee(HISTORY_SECTION, false);
});

it('shows on atrium\'s dashboard once only keen\'s screens are excluded, read when the screen renders', function (): void {
    config()->set('keen.atrium.history.exclude', ['keen.*']);

    $this->get(route('keen-test.detail', ['screen' => 'atrium.dashboard']))
        ->assertOk()
        ->assertSee(HISTORY_SECTION, false);
});

it('wraps an exclude pattern given as a string', function (): void {
    [$cog] = historySectionProduct('Cog');

    config()->set('keen.atrium.history.exclude', 'shop.*');

    $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))
        ->assertOk()
        ->assertDontSee(HISTORY_SECTION, false);
});

it('never reads the trail on a screen it refuses', function (Closure $refuse): void {
    $trail = historySectionCountingTrail();
    $cog = ProductModel::query()->create(['name' => 'Cog']);

    $url = $refuse($cog);

    $this->get($url)
        ->assertOk()
        ->assertSee('data-testid="host-page"', false)
        ->assertDontSee(HISTORY_SECTION, false);

    expect($trail->calls)->toBe(0);
})->with([
    'an excluded screen' => [fn (ProductModel $cog): string => route('keen-test.detail', ['screen' => 'atrium.dashboard'])],
    'a screen of an unregistered package' => [fn (ProductModel $cog): string => route('keen-test.detail', ['screen' => 'other.screen', 'record' => $cog->id])],
    'a package that does not audit' => [function (ProductModel $cog): string {
        config()->set('shop.audit.enabled', false);

        return route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]);
    }],
    'a viewer the history rule refuses' => [function (ProductModel $cog): string {
        app(PackageRegistry::class)->get('shop')->authorizeHistory(fn (): bool => false);

        return route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]);
    }],
    'a record that is not stored' => [fn (ProductModel $cog): string => route('keen-test.detail', ['screen' => 'shop.product', 'unsaved' => 1])],
]);

it('reads the trail once on a screen it shows on', function (): void {
    $trail = historySectionCountingTrail();
    $cog = ProductModel::query()->create(['name' => 'Cog']);

    $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))
        ->assertOk()
        ->assertSee(HISTORY_SECTION, false);

    expect($trail->calls)->toBe(1);
});

it('queries the audit table at most once on an audited detail screen, and never on a refused one', function (): void {
    [$cog] = historySectionProduct('Cog');
    historySectionProduct('Gear');

    // The bound: the trail's one page read, however many entries there are.
    $audited = historySectionAuditQueries(fn () => $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))
        ->assertOk()
        ->assertSee(HISTORY_SECTION, false));

    app()->forgetScopedInstances();

    $excluded = historySectionAuditQueries(fn () => $this->get(route('keen-test.detail', ['screen' => 'atrium.dashboard']))
        ->assertOk()
        ->assertDontSee(HISTORY_SECTION, false));

    app()->forgetScopedInstances();
    config()->set('shop.audit.enabled', false);

    $unaudited = historySectionAuditQueries(fn () => $this->get(route('keen-test.detail', ['screen' => 'shop.product', 'record' => $cog->id]))
        ->assertOk()
        ->assertDontSee(HISTORY_SECTION, false));

    expect($audited)->toBeGreaterThan(0)->toBeLessThanOrEqual(1)
        ->and($excluded)->toBe(0)
        ->and($unaudited)->toBe(0);
});
