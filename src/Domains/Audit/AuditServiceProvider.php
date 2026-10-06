<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use JayI\Foundation\Audit\Contracts\AuditTrail;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Foundation\Support\ServiceProvider;
use JayI\Keen\Domains\Audit\Console\Commands\ImportRosterAuditCommand;
use JayI\Keen\Domains\Audit\Console\Commands\PruneAuditCommand;
use JayI\Keen\Domains\Audit\Console\Commands\VerifyAuditCommand;
use JayI\Keen\Domains\Audit\Services\AuditLog;
use JayI\Keen\Domains\Audit\Services\AuditRecorder;
use JayI\Keen\Domains\Audit\Services\KeenAuditTrail;

/**
 * The hash-chained audit log, recorded from every package's action events
 * and read by every package through the shared `AuditTrail`.
 */
class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuditRecorder::class);
        $this->app->singleton(AuditLog::class);

        // Replaces the shared runtime's null trail, so every package's
        // history endpoint, tool and screen starts answering.
        $this->app->singleton(AuditTrail::class, KeenAuditTrail::class);
    }

    public function boot(): void
    {
        $this->registerRecorder();

        $this->loadApiRoutesFrom(__DIR__.'/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PruneAuditCommand::class, VerifyAuditCommand::class, ImportRosterAuditCommand::class]);
        }
    }

    /**
     * Record every package's changes from the shared action events, unless
     * `keen.enabled` turns recording off.
     */
    private function registerRecorder(): void
    {
        if ($this->app->make(Repository::class)->get('keen.enabled') !== true) {
            return;
        }

        $events = $this->app->make(Dispatcher::class);

        $events->listen(ActionStartingEvent::class, fn (ActionStartingEvent $event) => $this->app->make(AuditRecorder::class)->starting($event));
        $events->listen(ActionFinishedEvent::class, fn (ActionFinishedEvent $event) => $this->app->make(AuditRecorder::class)->finished($event));
    }
}
