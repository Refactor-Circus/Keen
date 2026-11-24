<?php

declare(strict_types=1);

/*
 * Keen's Atrium front-end module: the sources in `atrium/` build to the
 * committed `dist/atrium/`, which composer.json's `extra.atrium` declares
 * and Atrium loads. Hosts never run Node, so the build must be committed and
 * current. `build.json` holds the SHA-256 of the sources it was built from;
 * this recomputes it as the preset does (`hashInputs()` in
 * `@refactor-circus/atrium/vite`), so a source changed without `pnpm build`
 * fails here without Node. The `ui` CI job also rebuilds and diffs. The
 * integration manifest (`atrium/src/integration.json`, built to
 * `dist/atrium/integration.json`) declares Keen's nav, screens and hooks to
 * Atrium as JSON, with no Atrium PHP.
 */

/**
 * The SHA-256 of `inputs` under `root`, as the preset's `hashInputs()` takes it.
 *
 * @param  list<string>  $inputs
 */
function atriumSourcesHash(string $root, array $inputs): string
{
    $files = [];

    $walk = function (string $path) use (&$walk, &$files, $root): void {
        $absolute = $root.'/'.$path;

        if (! file_exists($absolute)) {
            return;
        }

        if (is_dir($absolute)) {
            foreach (scandir($absolute) ?: [] as $entry) {
                if (! in_array($entry, ['node_modules', 'dist', '.DS_Store', '.git'], true) && ! str_starts_with($entry, '.')) {
                    $walk($path.'/'.$entry);
                }
            }

            return;
        }

        $files[] = $path;
    };

    foreach ($inputs as $input) {
        $walk(rtrim($input, '/'));
    }

    $files = array_values(array_unique($files));
    sort($files, SORT_STRING);

    $lines = array_map(
        fn (string $path): string => $path."\0".hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$path)))."\n",
        $files,
    );

    return hash('sha256', implode('', $lines));
}

it('ships the built module that composer.json declares', function (): void {
    $root = dirname(__DIR__, 2);
    /** @var array{extra: array{atrium: array{scripts: list<string>, styles: list<string>}}} $manifest */
    $manifest = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $declared = [...$manifest['extra']['atrium']['scripts'], ...$manifest['extra']['atrium']['styles']];

    expect($declared)->toBe(['dist/atrium/atrium.js', 'dist/atrium/atrium.css']);

    foreach ($declared as $path) {
        expect($root.'/'.$path)->toBeFile();
    }
});

it('ships the integration manifest that composer.json declares', function (): void {
    $root = dirname(__DIR__, 2);
    /** @var array{extra: array{atrium: array{integration: string}}} $composer */
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $path = $composer['extra']['atrium']['integration'];

    expect($path)->toBe('dist/atrium/integration.json')
        ->and($root.'/'.$path)->toBeFile();

    /** @var array{key: string, version: int} $integration */
    $integration = json_decode((string) file_get_contents($root.'/'.$path), true, flags: JSON_THROW_ON_ERROR);

    expect($integration['key'])->toBe('keen')
        ->and($integration['version'])->toBe(1);
});

it('was built from the module sources as they are now', function (): void {
    $file = dirname(__DIR__, 2).'/dist/atrium/build.json';

    /** @var array{key: string, shared_api: string, root: string, inputs: list<string>, hash: string} $info */
    $info = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    $root = realpath(dirname($file).'/'.$info['root']);

    expect($root)->toBe(realpath(dirname(__DIR__, 2).'/atrium'))
        ->and($info['key'])->toBe('keen')
        ->and($info['shared_api'])->toBe('1.0')
        ->and(atriumSourcesHash((string) $root, $info['inputs']))->toBe($info['hash'], 'The Atrium module build is older than its sources: run `pnpm build` in atrium/ and commit dist/atrium.');
});
