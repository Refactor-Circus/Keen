<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditEntry;
use RefactorCircus\Keystone\Audit\Data\AuditFilter;
use RefactorCircus\Keystone\Audit\Data\AuditPage;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * @param  array<string, array{0: mixed, 1: mixed}>  $changes
 */
function keenComponentEntry(string $source = 'shop', array $changes = []): AuditEntry
{
    return new AuditEntry(
        id: 7,
        source: $source,
        action: 'product.updated',
        surface: 'atrium',
        createdAt: CarbonImmutable::now()->subMinutes(5),
        actorId: '1',
        actorLabel: 'Ada Lovelace',
        subjectType: 'product',
        subjectId: '42',
        subjectLabel: 'Widget',
        changes: $changes,
    );
}

/**
 * Bind an audit trail that answers with this page and keeps the filter it was asked with.
 */
function keenComponentTrail(AuditPage $page): object
{
    $trail = new class($page) implements AuditTrail
    {
        public ?AuditFilter $filter = null;

        public function __construct(private readonly AuditPage $page) {}

        public function available(): bool
        {
            return true;
        }

        public function entries(AuditFilter $filter): AuditPage
        {
            $this->filter = $filter;

            return $this->page;
        }
    };

    app()->instance(AuditTrail::class, $trail);

    return $trail;
}

it('renders nothing for the history while the source package does not audit', function (): void {
    keenComponentTrail(new AuditPage([keenComponentEntry()]));
    config()->set('shop.audit.enabled', false);

    expect(trim(Blade::render('<x-keen::audit-trail source="shop" />')))->toBe('');
});

it('renders a package history from the installed audit log', function (): void {
    $trail = keenComponentTrail(new AuditPage([keenComponentEntry()]));
    $product = ProductModel::query()->create(['name' => 'Cog']);

    $html = Blade::render('<x-keen::audit-trail source="shop" :subject="$subject" :scope="$subject" :limit="5" action="product." />', ['subject' => $product]);

    expect($html)->toContain('data-testid="audit-trail"')
        ->toContain('History')
        ->toContain('product.updated')
        ->toContain('Ada Lovelace')
        ->toContain('atrium')
        // One record's history leaves out the record column.
        ->not->toContain('Widget')
        ->and($trail->filter?->source)->toBe('shop')
        ->and($trail->filter?->subjectId)->toBe((string) $product->getKey())
        ->and($trail->filter?->limit)->toBe(5)
        ->and($trail->filter?->action)->toBe('product.')
        ->and($trail->filter?->scopeId)->toBe((string) $product->getKey());
});

it('links the trail to the package history page with its filters', function (): void {
    if (! Route::has('atrium.keen.history.show')) {
        // Keen's history page lands with the screens; stand in for it until then.
        Route::get('atrium/history/{package}', fn (): string => '')->name('atrium.keen.history.show');
        Route::getRoutes()->refreshNameLookups();
    }

    keenComponentTrail(new AuditPage([keenComponentEntry()], nextCursor: 'next'));
    $product = ProductModel::query()->create(['name' => 'Cog']);

    $html = Blade::render('<x-keen::audit-trail source="shop" :subject="$product" />', ['product' => $product]);

    expect($html)->toContain('data-testid="audit-trail-all"')
        ->toContain('View all')
        ->toContain('/atrium/history/shop?subject_type=')
        ->toContain('subject_id='.$product->getKey());
});

it('leaves out the view all link while the history page is not served', function (): void {
    app('router')->setRoutes(new RouteCollection);

    keenComponentTrail(new AuditPage([keenComponentEntry()], nextCursor: 'next'));

    expect(Blade::render('<x-keen::audit-trail source="shop" />'))
        ->toContain('product.updated')
        ->not->toContain('data-testid="audit-trail-all"');
});

it('links each entry time to the entry while the keen dashboard serves it', function (): void {
    expect(Route::has('atrium.keen.entries.show'))->toBeTrue();

    $html = Blade::render('<x-keen::audit.entries :entries="$entries" />', ['entries' => [keenComponentEntry()]]);

    expect($html)->toContain('href="'.route('atrium.keen.entries.show', 7).'"');
});

it('shows each entry time as plain text without the keen dashboard', function (): void {
    // What the component sees with `keen.ui.enabled` off: no dashboard routes.
    app('router')->setRoutes(new RouteCollection);

    expect(Route::has('atrium.keen.entries.show'))->toBeFalse();

    $entry = keenComponentEntry();
    $html = Blade::render('<x-keen::audit.entries :entries="$entries" />', ['entries' => [$entry]]);

    expect($html)->toContain('product.updated')
        ->toContain('<span title="'.$entry->createdAt->toIso8601String().'">')
        ->not->toContain('<a ');
});

it('marks entries the application recorded itself', function (): void {
    $html = Blade::render('<x-keen::audit.entries :entries="$entries" />', ['entries' => [keenComponentEntry('app')]]);

    expect($html)->toContain('Widget')->toMatch('/>\s*App\s*</');
});

it('shows an empty state without entries', function (): void {
    expect(Blade::render('<x-keen::audit.entries :entries="[]" />'))->toContain('Nothing has been recorded yet.');
});

it('lists field changes with their old and new values', function (): void {
    $html = Blade::render('<x-keen::audit.changes :changes="$changes" />', ['changes' => ['name' => ['Old', 'New'], 'tags' => [null, ['a']]]]);

    expect($html)->toContain('data-testid="audit-changes"')
        ->toContain('name')
        ->toContain('&#039;Old&#039;')
        ->toContain('&#039;New&#039;')
        ->toContain('NULL')
        ->toContain('[&quot;a&quot;]');
});

it('says when no fields changed', function (): void {
    expect(Blade::render('<x-keen::audit.changes :changes="[]" />'))->toContain('No fields changed.');
});

it('hides a package history from those its history endpoint would refuse', function (): void {
    keenComponentTrail(new AuditPage([keenComponentEntry('vault')]));

    app(PackageRegistry::class)->register(
        Package::make('vault', 'Vault')->authorizeHistory(fn (): bool => false),
    );

    expect(trim(Blade::render('<x-keen::audit-trail source="vault" />')))->toBe('');
});
