<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Actions;

use Illuminate\Support\Facades\DB;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductUpdatedActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events\ProductUpdatingActionEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Actions\Action;

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
