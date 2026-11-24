<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function composerManifest(): array
{
    /** @var array<string, mixed> $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    return $manifest;
}

it('requires no suite package but keystone', function (): void {
    $require = array_keys((array) composerManifest()['require']);

    expect(array_values(array_filter($require, fn (string $package): bool => str_starts_with($package, 'refactor-circus/'))))
        ->toBe(['refactor-circus/keystone']);
});

it('names atrium in no require, require-dev, suggest or conflict', function (string $section): void {
    expect((array) (composerManifest()[$section] ?? []))->not->toHaveKey('refactor-circus/atrium');
})->with(['require', 'require-dev', 'suggest', 'conflict']);

it('has no atrium repository', function (): void {
    $manifest = composerManifest();
    $urls = array_map(fn (mixed $repository): string => (string) ((array) $repository)['url'], (array) ($manifest['repositories'] ?? []));

    expect(array_values(array_filter($urls, fn (string $url): bool => stripos($url, 'atrium') !== false)))->toBe([]);
});

it('declares only its built front-end module in extra.atrium', function (): void {
    expect((array) ((array) composerManifest()['extra'])['atrium'])->toBe([
        'key' => 'keen',
        'integration' => 'dist/atrium/integration.json',
        'scripts' => ['dist/atrium/atrium.js'],
        'styles' => ['dist/atrium/atrium.css'],
    ]);
});

it('has no without-atrium suite', function (): void {
    $scripts = (array) composerManifest()['scripts'];

    expect($scripts)->not->toHaveKey('test:without-atrium')
        ->and(array_values((array) $scripts['test']))->not->toContain('@test:without-atrium')
        ->and(dirname(__DIR__, 2).'/phpunit.without-atrium.xml')->not->toBeFile()
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml'))->not->toContain('without-atrium');
});

/**
 * The steps of the `client` job in the tests workflow, as YAML text.
 */
function clientWorkflowJob(): string
{
    $workflow = str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml'));

    if (preg_match('/^  client:\n(.*?)(?=^  \S|\z)/ms', $workflow, $match) !== 1) {
        return '';
    }

    return $match[1];
}

it('regenerates the openapi document and the client in order', function (): void {
    $scripts = (array) composerManifest()['scripts'];

    expect(array_values((array) ($scripts['client:generate'] ?? [])))->toBe([
        '@php vendor/bin/testbench scramble:export --path=openapi.json',
        'npm --prefix client ci',
        'npm --prefix client run generate',
        '@php bin/client-stamp.php',
    ])->and(dirname(__DIR__, 2).'/bin/client-stamp.php')->toBeFile();
});

it('keeps node out of composer test', function (): void {
    $scripts = (array) composerManifest()['scripts'];

    expect(array_values((array) $scripts['test']))->not->toContain('@client:generate')
        ->and(array_values((array) $scripts['test:unit']))->not->toContain('@client:generate');
});

it('fails ci when the generated client is stale', function (): void {
    $job = clientWorkflowJob();

    expect($job)->not->toBe('', 'The tests workflow has no `client` job.')
        ->toContain('shivammathur/setup-php')
        ->toContain('php-version: 8.5')
        ->toContain('actions/setup-node')
        ->toContain('node-version: lts/*')
        ->toContain('composer install');

    $generate = strpos($job, 'composer client:generate');
    $diff = strpos($job, 'git diff --exit-code -- openapi.json client');

    expect($generate)->toBeInt()
        ->and($diff)->toBeInt()
        ->and($generate < $diff)->toBeTrue();
});

it('ships openapi.json and the client in git archives', function (): void {
    $lines = array_map(trim(...), file(dirname(__DIR__, 2).'/.gitattributes') ?: []);
    $ignored = [];

    foreach ($lines as $line) {
        if (str_contains($line, 'export-ignore')) {
            $ignored[] = trim((string) strtok($line, " \t"), '/');
        }
    }

    expect($ignored)->not->toContain('client')
        ->not->toContain('openapi.json')
        ->not->toContain('client/dist');
});

it('ships the built atrium module but not its sources in git archives', function (): void {
    $lines = array_map(trim(...), file(dirname(__DIR__, 2).'/.gitattributes') ?: []);
    $ignored = [];

    foreach ($lines as $line) {
        if (str_contains($line, 'export-ignore')) {
            $ignored[] = trim((string) strtok($line, " \t"), '/');
        }
    }

    expect($ignored)->toContain('atrium')
        ->not->toContain('dist')
        ->not->toContain('dist/atrium');
});

it('describes keen as a json api and mcp, not atrium screens', function (): void {
    expect((string) composerManifest()['description'])
        ->toContain('JSON API and MCP')
        ->not->toContain('Atrium');
});
