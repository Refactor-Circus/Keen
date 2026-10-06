<?php

declare(strict_types=1);

namespace JayI\Keen\Domains;

use Illuminate\Support\ServiceProvider;
use JayI\Keen\Domains\Audit\AuditServiceProvider;

/**
 * Registers every domain module's service provider.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(AuditServiceProvider::class);
    }
}
