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

/**
 * The `without-atrium` job of the tests workflow, or '' when it is missing.
 */
function withoutAtriumJob(): string
{
    $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');

    return preg_match('/^  without-atrium:\n(?:(?:    .*)?\n)*/m', $workflow, $match) === 1 ? $match[0] : '';
}

it('requires no suite package but keystone', function (): void {
    $require = array_keys((array) composerManifest()['require']);

    expect(array_values(array_filter($require, fn (string $package): bool => str_starts_with($package, 'refactor-circus/'))))
        ->toBe(['refactor-circus/keystone']);
});

it('keeps atrium a suggestion and a dev dependency', function (): void {
    $manifest = composerManifest();

    expect($manifest['suggest'])->toHaveKey('refactor-circus/atrium')
        ->and($manifest['require-dev'])->toHaveKey('refactor-circus/atrium')
        ->and(((array) $manifest['require-dev'])['refactor-circus/atrium'])->toBe('dev-main');
});

it('conflicts with atrium releases from before the screen registry', function (): void {
    expect(composerManifest()['conflict'] ?? null)->toBe(['refactor-circus/atrium' => '<0.2.0']);
});

it('runs the without-atrium suite from composer test, after the unit tests', function (): void {
    $scripts = (array) composerManifest()['scripts'];
    $test = array_values((array) $scripts['test']);

    expect($scripts['test:without-atrium'] ?? null)->toBe(['vendor/bin/pest --configuration=phpunit.without-atrium.xml'])
        ->and($test)->toContain('@test:without-atrium')
        ->and(array_search('@test:without-atrium', $test, true))->toBeGreaterThan(array_search('@test:unit', $test, true));
});

it('runs the without-atrium suite in ci with atrium uninstalled', function (): void {
    $job = withoutAtriumJob();

    expect($job)->not->toBe('')
        ->toContain('composer remove --dev refactor-circus/atrium --no-update')
        ->toContain('composer test:without-atrium');

    expect(strpos($job, 'composer remove --dev refactor-circus/atrium --no-update'))
        ->toBeLessThan(strpos($job, 'composer test:without-atrium'));
});
