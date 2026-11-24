<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Shop\Domains\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Routed by its slug, not its key, as a host's records often are: what a
 * screen passes as the record is the slug (D-019).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $price
 * @property string|null $secret
 */
final class ProductModel extends Model
{
    protected $table = 'products';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        self::creating(function (self $product): void {
            $product->slug ??= Str::slug($product->name).'-'.Str::lower(Str::random(6));
        });
    }
}
