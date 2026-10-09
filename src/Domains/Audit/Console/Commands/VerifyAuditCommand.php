<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Console\Commands;

use Illuminate\Console\Command;
use RefactorCircus\Keen\Domains\Audit\Services\AuditLog;

final class VerifyAuditCommand extends Command
{
    protected $signature = 'keen:verify';

    protected $description = "Check the audit log's hash chain for entries altered after they were written";

    public function handle(AuditLog $log): int
    {
        $broken = $log->verify();

        if ($broken !== null) {
            $this->components->error("The audit log was altered: the chain breaks at entry [{$broken}].");

            return self::FAILURE;
        }

        $this->components->info('The audit log is intact.');

        return self::SUCCESS;
    }
}
