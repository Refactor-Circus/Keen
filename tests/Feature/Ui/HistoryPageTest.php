<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RefactorCircus\Keen\Atrium\Http\Controllers\HistoryUiController;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keen\Facades\Keen;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/*
 * One package's audit log at `atrium.keen.history.show` (criteria 6, 7 and
 * 15), ported from Atrium's HistoryPageTest against the `shop` fixture.
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    app(PackageRegistry::class)->register(Package::make('other', 'Vendor\\Other')->label('Other'));

    $this->actingAs(user());
});

/**
 * Record an entry as a package, about a record when given one.
 */
function historyPageRecord(string $action, string $source = 'shop', ?ProductModel $subject = null): AuditEntryModel
{
    $pending = Keen::record($action);

    if ($source !== 'app') {
        $pending->source($source);
    }

    if ($subject !== null) {
        $pending->on($subject);
    }

    return $pending->save();
}

/**
 * The upper bound a rule list puts on a value, such as `max:100`.
 *
 * @param  array<int, mixed>  $rules
 */
function historyPageMax(array $rules): int
{
    foreach ($rules as $rule) {
        if (is_string($rule) && str_starts_with($rule, 'max:')) {
            return (int) substr($rule, 4);
        }
    }

    throw new RuntimeException('The rules set no maximum.');
}

it('serves the history page at history/{package} under the dashboard prefix', function (): void {
    expect(route('atrium.keen.history.show', 'shop'))->toBe(url('/atrium/history/shop'));

    // Nothing else claims the path: it reaches Keen's controller.
    $route = app('router')->getRoutes()->match(Request::create('/atrium/history/shop'));

    expect($route->getName())->toBe('atrium.keen.history.show')
        ->and($route->getActionName())->toBe(HistoryUiController::class.'@show');

    $this->get('/atrium/history/shop')->assertOk()->assertSee('Shop audit log');
});

it('answers 404 for an unknown package', function (): void {
    $this->get(route('atrium.keen.history.show', 'nope'))->assertNotFound();
});

it('answers 404 for a package that does not audit', function (): void {
    config()->set('shop.audit.enabled', false);

    $this->get(route('atrium.keen.history.show', 'shop'))->assertNotFound();
});

it('redirects back with errors for a record key without its type', function (): void {
    expect(History::rules()['subject_type'])->toContain('required_with:subject_id');

    $this->from(route('atrium.keen.entries.index'))
        ->get(route('atrium.keen.history.show', ['shop', 'subject_id' => '1']))
        ->assertStatus(302)
        ->assertSessionHasErrors('subject_type');
});

it('redirects back with errors for a page larger than the rules allow', function (): void {
    /** @var array<int, mixed> $rules */
    $rules = History::rules()['per_page'];
    $max = historyPageMax($rules);

    $this->from(route('atrium.keen.entries.index'))
        ->get(route('atrium.keen.history.show', ['shop', 'per_page' => $max + 1]))
        ->assertStatus(302)
        ->assertSessionHasErrors('per_page');

    $this->get(route('atrium.keen.history.show', ['shop', 'per_page' => $max]))->assertOk();
});

it('refuses those the package history rule refuses', function (): void {
    app(PackageRegistry::class)->get('shop')->authorizeHistory(fn (): bool => false);

    $this->get(route('atrium.keen.history.show', 'shop'))->assertForbidden();
});

it('validates before it authorizes', function (): void {
    app(PackageRegistry::class)->get('shop')->authorizeHistory(fn (): bool => false);

    $this->get(route('atrium.keen.history.show', ['shop', 'subject_id' => '1']))
        ->assertStatus(302)
        ->assertSessionHasErrors('subject_type');
});

it('lists only the package\'s own entries', function (): void {
    historyPageRecord('product.created');
    historyPageRecord('invoice.paid', 'app');
    historyPageRecord('other.thing', 'other');

    $this->get(route('atrium.keen.history.show', 'shop'))
        ->assertOk()
        ->assertSee('Shop audit log')
        ->assertSee('product.created')
        ->assertDontSee('invoice.paid')
        ->assertDontSee('other.thing');
});

it('keeps the route package as the source whatever the query string says', function (): void {
    historyPageRecord('product.created');
    historyPageRecord('other.thing', 'other');

    $this->get(route('atrium.keen.history.show', ['shop', 'source' => 'other']))
        ->assertOk()
        ->assertSee('product.created')
        ->assertDontSee('other.thing');
});

it('filters by action and record', function (): void {
    $cog = ProductModel::query()->create(['name' => 'Cog']);
    $gear = ProductModel::query()->create(['name' => 'Gear']);

    historyPageRecord('product.created', subject: $cog);
    historyPageRecord('product.renamed', subject: $cog);
    historyPageRecord('stock.counted', subject: $cog);
    historyPageRecord('product.priced', subject: $gear);
    historyPageRecord('invoice.paid', 'app');
    historyPageRecord('other.thing', 'other');

    $this->get(route('atrium.keen.history.show', [
        'shop',
        'action' => 'product.',
        'subject_type' => $cog->getMorphClass(),
        'subject_id' => (string) $cog->getKey(),
    ]))
        ->assertOk()
        ->assertSee('product.created')
        ->assertSee('product.renamed')
        ->assertDontSee('stock.counted')
        ->assertDontSee('product.priced')
        ->assertDontSee('invoice.paid')
        ->assertDontSee('other.thing');
});

it('pages through older entries by cursor', function (): void {
    foreach (range(1, 5) as $i) {
        historyPageRecord('product.step'.$i);
    }

    $first = $this->get(route('atrium.keen.history.show', ['shop', 'per_page' => 2]))
        ->assertOk()
        ->assertSee('product.step5')
        ->assertSee('product.step4')
        ->assertDontSee('product.step3')
        ->assertSee('data-testid="history-older"', false)
        ->assertDontSee('Newest');

    // The "Older" link carries the next page's cursor.
    expect(preg_match('/<a href="([^"]*)"[^>]*data-testid="history-older"/', (string) $first->getContent(), $match))->toBe(1)
        ->and($match[1])->toContain('cursor=');

    $second = $this->get(html_entity_decode($match[1]))
        ->assertOk()
        ->assertSee('product.step3')
        ->assertSee('product.step2')
        ->assertDontSee('product.step4')
        ->assertSee('Newest')
        ->assertSee('data-testid="history-older"', false);

    expect((string) $second->getContent())->toContain('per_page=2');
});
