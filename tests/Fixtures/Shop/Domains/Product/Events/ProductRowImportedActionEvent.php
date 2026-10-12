<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models\ProductModel;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;
use RefactorCircus\Keystone\Contracts\UnauditedActionEvent;

/**
 * One row of a product import was applied: a per-row step.
 */
final class ProductRowImportedActionEvent implements ActionFinishedEvent, UnauditedActionEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ProductModel $product,
    ) {}
}
