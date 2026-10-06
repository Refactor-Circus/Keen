<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use JayI\Foundation\Audit\AuditHooks;
use JayI\Foundation\Support\Surface;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use JayI\Keen\Domains\Audit\Services\Labels;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Actions\UpdateProductAction;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductCreatedActionEvent;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductsListedActionEvent;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

it('records another package\'s action with its changes', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog', 'price' => 100]);
    Auth::login($ada = user());

    app(UpdateProductAction::class)->execute($product, ['name' => 'Sprocket', 'price' => 120]);

    $entry = AuditEntryModel::query()->sole();

    expect($entry->source)->toBe('shop')
        ->and($entry->action)->toBe('product.updated')
        ->and($entry->subject_type)->toBe(ProductModel::class)
        ->and($entry->subject_id)->toBe((string) $product->id)
        ->and($entry->subject_label)->toBe('Sprocket')
        ->and($entry->actor_id)->toBe((string) $ada->id)
        ->and($entry->actor_label)->toBe('Ada Lovelace')
        ->and($entry->surface)->toBe('cli')
        ->and($entry->changes)->toBe(['name' => ['Cog', 'Sprocket'], 'price' => [100, 120]]);
});

it('lists every field of a created record', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog', 'price' => 5]);

    ProductCreatedActionEvent::dispatch($product);

    expect(AuditEntryModel::query()->sole()->changes)->toMatchArray(['name' => [null, 'Cog'], 'price' => [null, 5]]);
});

it('does not record reads', function (): void {
    ProductsListedActionEvent::dispatch(ProductModel::query()->create(['name' => 'Cog']));

    expect(AuditEntryModel::query()->count())->toBe(0);
});

it('ignores packages and events keen.ignore names', function (): void {
    config()->set('keen.ignore', ['shop']);

    ProductCreatedActionEvent::dispatch(ProductModel::query()->create(['name' => 'Cog']));

    expect(AuditEntryModel::query()->count())->toBe(0);
});

it('redacts secrets but still records that they changed', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog', 'secret' => 'one']);

    app(UpdateProductAction::class)->execute($product, ['secret' => 'two']);

    expect(AuditEntryModel::query()->sole()->changes)->toBe(['secret' => ['[redacted]', '[redacted]']]);
});

it('uses the labels, snapshots, context and scope packages register', function (): void {
    $scope = ProductModel::query()->create(['name' => 'Scope']);
    $product = ProductModel::query()->create(['name' => 'Cog', 'price' => 1]);

    app(AuditHooks::class)
        ->label(ProductModel::class, fn (ProductModel $model): string => 'Product '.$model->name)
        ->snapshot(ProductModel::class, fn (ProductModel $model): array => ['price_band' => $model->price > 10 ? 'high' : 'low'])
        ->context(fn (): array => ['impersonator' => 'grace'])
        ->scope(fn (): ProductModel => $scope)
        ->redact('price');

    app(UpdateProductAction::class)->execute($product, ['price' => 50]);

    $entry = AuditEntryModel::query()->sole();

    expect($entry->subject_label)->toBe('Product Cog')
        ->and($entry->changes)->toBe(['price' => ['[redacted]', '[redacted]'], 'price_band' => ['low', 'high']])
        ->and($entry->context)->toMatchArray(['impersonator' => 'grace'])
        ->and($entry->scope_id)->toBe((string) $scope->id);
});

it('records the surface the change came through', function (): void {
    $product = ProductModel::query()->create(['name' => 'Cog']);

    app(Surface::class)->using('mcp', fn () => app(UpdateProductAction::class)->execute($product, ['name' => 'Gear']));

    expect(AuditEntryModel::query()->sole()->surface)->toBe('mcp');
});

it('never resolves a label method as a relation', function (): void {
    $model = new class extends Model
    {
        public function label(): string
        {
            return 'not a relation';
        }
    };

    $model->forceFill(['id' => 9]);

    expect(app(Labels::class)->for($model))->toBe('9');
});
