<?php

declare(strict_types=1);

use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Boots Scramble and the workbench provider, which holds the one Scramble
 * configuration `composer client:generate` and this test both read.
 */
trait ExportsOpenApi
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ScrambleServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }
}

uses(ExportsOpenApi::class);

const KEEN_OPENAPI_REGENERATE = 'openapi.json is out of date: run `composer client:generate` and commit the result.';

/**
 * The committed document at the package root, as a string.
 */
function keenCommittedOpenApi(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/openapi.json');
}

/**
 * Export the document as `composer client:generate` does, into a temporary
 * file, and return its contents.
 */
function keenExportedOpenApi(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'keen-openapi-');

    try {
        expect(Artisan::call('scramble:export', ['--path' => $path]))->toBe(0);

        return (string) file_get_contents($path);
    } finally {
        @unlink($path);
    }
}

/**
 * @return array<string, mixed>
 */
function keenDecodedOpenApi(?string $json = null): array
{
    /** @var array<string, mixed> $document */
    $document = json_decode($json ?? keenCommittedOpenApi(), true, flags: JSON_THROW_ON_ERROR);

    return $document;
}

/**
 * The value at a path of keys, or null when any key is missing.
 *
 * @param  array<array-key, mixed>  $data
 */
function keenDig(array $data, string ...$keys): mixed
{
    $value = $data;

    foreach ($keys as $key) {
        if (! is_array($value) || ! array_key_exists($key, $value)) {
            return null;
        }

        $value = $value[$key];
    }

    return $value;
}

/**
 * Follow a local `$ref` (`#/components/...`) to the value it names.
 *
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function keenOpenApiRef(array $document, string $ref): array
{
    $keys = array_map(
        fn (string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
        explode('/', substr($ref, 2)),
    );

    return (array) keenDig($document, ...$keys);
}

/**
 * The component schemas a part of the document reaches through `$ref`s,
 * followed transitively.
 *
 * @param  array<string, mixed>  $document
 * @param  array<array-key, mixed>  $node
 * @param  array<string, true>  $seen
 * @return array<string, true>
 */
function keenReachedSchemas(array $document, array $node, array $seen = []): array
{
    foreach ($node as $key => $value) {
        if ($key === '$ref' && is_string($value) && ! isset($seen[$value])) {
            $seen[$value] = true;
            $seen = keenReachedSchemas($document, keenOpenApiRef($document, $value), $seen);
        } elseif (is_array($value)) {
            $seen = keenReachedSchemas($document, $value, $seen);
        }
    }

    return $seen;
}

/**
 * An operation of the committed document.
 *
 * @return array<string, mixed>
 */
function keenOperation(string $method, string $path): array
{
    return (array) keenDig(keenDecodedOpenApi(), 'paths', $path, $method);
}

/**
 * The schemas the 2xx `application/json` responses of an operation reach.
 *
 * @return array<int, string>
 */
function keenSuccessSchemas(string $method, string $path): array
{
    $document = keenDecodedOpenApi();
    $schemas = [];

    foreach ((array) (keenOperation($method, $path)['responses'] ?? []) as $status => $response) {
        $response = (array) $response;

        if (isset($response['$ref']) && is_string($response['$ref'])) {
            $response = keenOpenApiRef($document, $response['$ref']);
        }

        $schema = keenDig($response, 'content', 'application/json', 'schema');

        if (str_starts_with((string) $status, '2') && is_array($schema)) {
            foreach (array_keys(keenReachedSchemas($document, ['schema' => $schema])) as $ref) {
                $schemas[] = (string) $ref;
            }
        }
    }

    return $schemas;
}

it('matches the committed openapi.json when exported again', function (): void {
    expect(keenDecodedOpenApi(keenExportedOpenApi()))
        ->toBe(keenDecodedOpenApi(), KEEN_OPENAPI_REGENERATE);
});

it('exports byte-identical documents twice in a row', function (): void {
    expect(keenExportedOpenApi())->toBe(keenExportedOpenApi());
});

it('describes Keen 2.0.0 as an OpenAPI 3.1 document', function (): void {
    $document = keenDecodedOpenApi();
    $info = (array) $document['info'];

    expect((string) $document['openapi'])->toStartWith('3.1')
        ->and($info['version'])->toBe('2.0.0')
        ->and((string) $info['title'])->toContain('Keen');
});

it('serves every operation under the keen route prefix', function (): void {
    $servers = array_column((array) keenDecodedOpenApi()['servers'], 'url');

    expect($servers)->not->toBeEmpty();

    foreach ($servers as $url) {
        expect((string) $url)->toEndWith('/api/keen');
    }
});

it('has exactly one operation for each json api route', function (): void {
    $operations = [];

    foreach ((array) keenDecodedOpenApi()['paths'] as $path => $item) {
        foreach (array_keys((array) $item) as $method) {
            if (in_array($method, ['get', 'put', 'post', 'patch', 'delete', 'head', 'options', 'trace'], true)) {
                $operations[] = strtoupper($method).' '.$path;
            }
        }
    }

    sort($operations);

    expect($operations)->toBe([
        'GET /entries',
        'GET /entries/{entry}',
        'GET /packages',
        'POST /entries',
    ]);
});

it('answers every entries operation with AuditEntryResource', function (string $method, string $path): void {
    expect(keenSuccessSchemas($method, $path))->toContain('#/components/schemas/AuditEntryResource');
})->with([
    'index' => ['get', '/entries'],
    'store' => ['post', '/entries'],
    'show' => ['get', '/entries/{entry}'],
]);

it('answers the packages operation with AuditedPackageResource', function (): void {
    expect(keenSuccessSchemas('get', '/packages'))->toContain('#/components/schemas/AuditedPackageResource');

    $schema = keenOpenApiRef(keenDecodedOpenApi(), '#/components/schemas/AuditedPackageResource');

    expect(array_keys((array) $schema['properties']))->toEqualCanonicalizing(['key', 'label'])
        ->and((array) $schema['required'])->toEqualCanonicalizing(['key', 'label']);
});

it('describes the AuditEntryResource fields', function (): void {
    $schema = keenOpenApiRef(keenDecodedOpenApi(), '#/components/schemas/AuditEntryResource');

    expect(array_keys((array) ($schema['properties'] ?? [])))->toContain('id', 'source', 'action', 'hash', 'created_at');
});

it('lists the entries filters as query parameters', function (): void {
    $names = [];

    foreach ((array) (keenOperation('get', '/entries')['parameters'] ?? []) as $parameter) {
        $parameter = (array) $parameter;

        if (($parameter['in'] ?? null) === 'query') {
            $names[] = (string) $parameter['name'];
        }
    }

    expect($names)->toContain('source', 'action', 'cursor', 'per_page');
});

it('takes a json body with an action when recording an entry', function (): void {
    $document = keenDecodedOpenApi();
    $schema = (array) keenDig(keenOperation('post', '/entries'), 'requestBody', 'content', 'application/json', 'schema');

    if (isset($schema['$ref']) && is_string($schema['$ref'])) {
        $schema = keenOpenApiRef($document, $schema['$ref']);
    }

    expect(array_keys((array) ($schema['properties'] ?? [])))->toContain('action');
});

it('installs Scramble for development only', function (): void {
    /** @var array{require: array<string, string>, 'require-dev': array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require-dev'])->toHaveKey('dedoc/scramble')
        ->and($composer['require'])->not->toHaveKey('dedoc/scramble');
});

/**
 * A file of the generated client, as a string.
 */
function keenClientFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/client/'.$path);
}

/**
 * @return array<string, mixed>
 */
function keenClientManifest(): array
{
    /** @var array<string, mixed> $manifest */
    $manifest = json_decode(keenClientFile('package.json'), true, flags: JSON_THROW_ON_ERROR);

    return $manifest;
}

it('stamps the client with the committed openapi.json', function (): void {
    $stamp = trim(keenClientFile('openapi.sha256'));
    $expected = hash('sha256', str_replace("\r\n", "\n", keenCommittedOpenApi()));

    expect($stamp)->toBe($expected, 'The TypeScript client is out of date: run `composer client:generate` and commit the result.');
});

it('commits the generated schema and the built client', function (string $path): void {
    expect(dirname(__DIR__, 2).'/client/'.$path)->toBeFile();
})->with([
    'src/schema.d.ts',
    'src/index.ts',
    'dist/index.js',
    'dist/index.d.ts',
    'package-lock.json',
    'tsconfig.json',
]);

it('publishes the client as an esm package built into dist', function (): void {
    $manifest = keenClientManifest();

    expect($manifest['name'])->toBe('@refactor-circus/keen-client')
        ->and($manifest['version'])->toBe('2.0.0')
        ->and($manifest['type'])->toBe('module')
        ->and($manifest['types'])->toBe('./dist/index.d.ts')
        ->and($manifest['exports'])->toBe([
            '.' => [
                'types' => './dist/index.d.ts',
                'import' => './dist/index.js',
            ],
        ]);
});

it('depends on openapi-fetch only and pins its generators exactly', function (): void {
    $manifest = keenClientManifest();
    $devDependencies = (array) $manifest['devDependencies'];

    expect(array_keys((array) $manifest['dependencies']))->toBe(['openapi-fetch'])
        ->and($devDependencies)->toHaveKeys(['openapi-typescript', 'typescript']);

    foreach (['openapi-typescript', 'typescript'] as $package) {
        expect((string) $devDependencies[$package])->toMatch('/^\d+\.\d+\.\d+$/');
    }
});

it('generates the schema from openapi.json and builds with tsc', function (): void {
    expect((array) keenClientManifest()['scripts'])
        ->toHaveKey('generate', 'openapi-typescript ../openapi.json -o src/schema.d.ts --empty-objects-unknown && tsc -p tsconfig.json');
});

it('types free-form objects as unknown values, not never', function (): void {
    expect(keenClientFile('src/schema.d.ts'))->not->toMatch('/^\s+(changes|context): Record<string, never>;/m');
});

it('exports the typed client and the resource aliases', function (string $file): void {
    $source = keenClientFile($file);

    expect($source)->toMatch('/export (declare )?function createKeenClient\(/')
        ->toMatch('/export type KeenClient\b/')
        ->toMatch('/export type AuditEntry\b/')
        ->toMatch('/export type AuditedPackage\b/')
        ->toMatch('/export type \{[^}]*\bpaths\b[^}]*\}/')
        ->toMatch('/export type \{[^}]*\bcomponents\b[^}]*\}/');
})->with(['src/index.ts', 'dist/index.d.ts']);

it('ignores the client node_modules', function (): void {
    $ignored = array_map(trim(...), file(dirname(__DIR__, 2).'/.gitignore') ?: []);

    expect($ignored)->toContain('/client/node_modules');
});

it('holds no machine-specific value', function (): void {
    $json = keenCommittedOpenApi();
    $root = dirname(__DIR__, 2);

    expect($json)->not->toContain($root)
        ->not->toContain('base64:')
        ->not->toContain('file://')
        ->not->toMatch('#/(Users|home|private|tmp|var/folders)/#');
});
