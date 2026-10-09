<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionStartingEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/**
 * A product about to be updated.
 */
final class ProductUpdatingActionEvent implements ActionStartingEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public ProductModel $product,
        public array $data,
    ) {}
}
