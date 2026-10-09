<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Foundation\Contracts\ActionFinishedEvent;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;

/**
 * A product was updated.
 */
final class ProductUpdatedActionEvent implements ActionFinishedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ProductModel $product,
    ) {}
}
