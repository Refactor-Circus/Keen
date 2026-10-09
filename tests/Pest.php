<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use RefactorCircus\Keen\Tests\TestCase;

uses(TestCase::class)->in('Feature');

function user(string $name = 'Ada Lovelace'): User
{
    return User::forceCreate(['name' => $name, 'email' => str($name)->slug().'@example.com', 'password' => 'secret']);
}
