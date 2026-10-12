<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Support;

use RefactorCircus\Keystone\Database\Tables as KeystoneTables;

/**
 * The table each of Keen's records lives in, from the `keen.tables` config
 * key. The model, the migration and the hash chain all ask here, so renaming
 * a table is one config change made before the migrations run.
 *
 * A published config that lists only some tables keeps the defaults for the
 * rest.
 */
final class Tables extends KeystoneTables
{
    protected const string CONFIG = 'keen.tables';

    /**
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'entries' => 'keen_entries',
        'chain' => 'keen_chain',
    ];
}
