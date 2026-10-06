<?php

declare(strict_types=1);

namespace JayI\Keen\Tests\Fixtures\Shop;

use JayI\Foundation\Packages\Package;
use JayI\Foundation\Support\PackageServiceProvider;

/**
 * Another package of the suite, whose actions Keen records.
 */
final class ShopServiceProvider extends PackageServiceProvider
{
    protected function definition(): Package
    {
        return Package::make('shop', __NAMESPACE__)->label('Shop');
    }

    public function register(): void
    {
        $this->registerPackage();
    }

    public function boot(): void
    {
        $this->loadHistoryRoutes();
    }
}
