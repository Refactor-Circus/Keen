<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Keen\Mcp\KeenServer;
use RefactorCircus\Keen\Tests\RenamedTablesTestCase;
use RefactorCircus\Keen\Tests\TestCase;
use RefactorCircus\Keystone\Testing\McpTools;
use RefactorCircus\Keystone\Testing\Parity;

uses(TestCase::class)->in('Feature');
uses(RenamedTablesTestCase::class)->in('Modes/RenamedTables');

function user(string $name = 'Ada Lovelace'): User
{
    return User::forceCreate(['name' => $name, 'email' => str($name)->slug().'@example.com', 'password' => 'secret']);
}

/**
 * Call a Keen MCP tool as a client does, with its output schema checked.
 *
 * @param  class-string<Tool>  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpTool(string $tool, array $arguments = []): TestResponse
{
    return McpTools::call(KeenServer::class, $tool, $arguments);
}

/**
 * Actions missing from the JSON API, MCP or the Atrium section.
 *
 * @return array<string, array<int, string>>
 */
function parityGaps(): array
{
    $root = dirname(__DIR__).'/src';

    return Parity::gaps($root.'/Domains/*/Actions/*.php', [
        'http' => $root.'/Domains/*/Http/Requests/*.php',
        'mcp' => $root.'/Domains/*/Mcp/Requests/*.php',
        'ui' => $root.'/Atrium/Http/Controllers/*.php',
    ]);
}
