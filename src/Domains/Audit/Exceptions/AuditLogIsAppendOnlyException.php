<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Exceptions;

use RuntimeException;

final class AuditLogIsAppendOnlyException extends RuntimeException
{
    public static function forEntry(int|string|null $id): self
    {
        return new self("Audit entry [{$id}] cannot be changed or deleted: the audit log is append-only. Use keen:prune to remove old entries.");
    }
}
