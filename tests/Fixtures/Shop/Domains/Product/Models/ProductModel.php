<?php

declare(strict_types=1);

namespace JayI\Keen\Tests\Fixtures\Shop\Domains\Product\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $price
 * @property string|null $secret
 */
final class ProductModel extends Model
{
    protected $table = 'products';

    protected $guarded = [];
}
