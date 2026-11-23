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

it('has no atrium repository and no atrium extra', function (): void {
    $manifest = composerManifest();
    $urls = array_map(fn (mixed $repository): string => (string) ((array) $repository)['url'], (array) ($manifest['repositories'] ?? []));

    expect(array_values(array_filter($urls, fn (string $url): bool => stripos($url, 'atrium') !== false)))->toBe([])
        ->and((array) ($manifest['extra'] ?? []))->not->toHaveKey('atrium');
});

it('has no without-atrium suite', function (): void {
    $scripts = (array) composerManifest()['scripts'];

    expect($scripts)->not->toHaveKey('test:without-atrium')
        ->and(array_values((array) $scripts['test']))->not->toContain('@test:without-atrium')
        ->and(dirname(__DIR__, 2).'/phpunit.without-atrium.xml')->not->toBeFile()
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml'))->not->toContain('without-atrium');
});

it('describes keen as a json api and mcp, not atrium screens', function (): void {
    expect((string) composerManifest()['description'])
        ->toContain('JSON API and MCP')
        ->not->toContain('Atrium');
});
