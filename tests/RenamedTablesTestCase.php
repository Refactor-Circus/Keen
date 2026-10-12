<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests;

use RefactorCircus\Keen\Support\Tables;

/**
 * Every Keen table renamed through `keen.tables` before migrating.
 */
abstract class RenamedTablesTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (array_keys(Tables::DEFAULTS) as $key) {
            $app['config']->set('keen.tables.'.$key, 'crm_'.$key);
        }
    }
}
