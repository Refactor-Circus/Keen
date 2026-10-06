<?php

declare(strict_types=1);

namespace JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Actions;

use Illuminate\Support\Facades\DB;
use JayI\Foundation\Actions\Action;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductUpdatedActionEvent;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductUpdatingActionEvent;
use JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

final class UpdateProductAction extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handle(ProductModel $product, array $data): ProductModel
    {
        ProductUpdatingActionEvent::dispatch($product, $data);

        $product = DB::transaction(fn (): ProductModel => tap($product)->update($data));

        ProductUpdatedActionEvent::dispatch($product);

        return $product;
    }
}
