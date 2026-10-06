<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Console\Commands;

use Illuminate\Console\Command;
use JayI\Keen\Domains\Audit\Services\AuditLog;

final class PruneAuditCommand extends Command
{
    protected $signature = 'keen:prune {--days= : Keep this many days instead of keen.retention_days}';

    protected $description = 'Delete audit entries older than the retention period';

    public function handle(AuditLog $log): int
    {
        $option = $this->option('days');
        $days = is_numeric($option) ? (int) $option : config('keen.retention_days');

        if (! is_numeric($days)) {
            $this->components->info('Audit retention is unlimited; nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = $log->prune((int) $days);

        $this->components->info("Pruned {$deleted} audit entries older than {$days} days.");

        return self::SUCCESS;
    }
}
