<?php

namespace Workbench\App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\ServiceProvider;
use RefactorCircus\Keen\Domains\Audit\Http\Resources\AuditedPackageResource;
use RefactorCircus\Keen\Domains\Audit\Resources\AuditEntryResource;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * Configures Scramble, which exports Keen's JSON API as openapi.json.
 * `composer client:generate` (through testbench.yaml) and
 * tests/Feature/OpenApiTest.php both read this one configuration, so the
 * committed document and the staleness test cannot drift apart. Nothing in
 * src/ knows about Scramble.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * The API resources whose component schema is their own `schema()`, the
     * JSON Schema their MCP tools already declare as output.
     *
     * @var list<class-string<JsonResource>>
     */
    private const array RESOURCES = [
        AuditEntryResource::class,
        AuditedPackageResource::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // A fixed host, so the document holds nothing machine-specific.
        config(['app.url' => 'http://localhost']);

        // Read Keen's prefix once every provider has registered and merged
        // its config, whatever order the providers come in.
        $this->app->booting(function (): void {
            $prefix = config('keen.routes.prefix');

            config([
                // Paths relative to the prefix (/entries, /packages), under
                // one server ending in it, so the document fits any host.
                'scramble.api_path' => is_string($prefix) ? trim($prefix, '/') : 'api/keen',
                // Always generate: a cached document could be stale.
                'scramble.cache.store' => null,
                'scramble.ui.title' => 'Keen',
                'scramble.info.version' => '2.0.0',
                'scramble.info.description' => 'The JSON API of Keen, the tamper-evident audit log of the Refactor Circus suite: read and record audit entries, and list the packages whose history the caller may read.',
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $this->useResourceSchemas($openApi);
        });
    }

    /**
     * Scramble infers a resource's fields from its model, and the types it
     * finds depend on whether the audit table exists in the database it
     * reads (`changes` comes out as a string, or as an array or a string).
     * Each resource declares its fields as JSON Schema already, for MCP: use
     * that, so the document is the same on every machine and matches the
     * tools' output schemas.
     */
    private function useResourceSchemas(OpenApi $openApi): void
    {
        $factory = new JsonSchemaTypeFactory;

        foreach (self::RESOURCES as $resource) {
            // Scramble names a resource's component by its class basename.
            $name = class_basename($resource);

            if (! $openApi->components->hasSchema($name)) {
                continue;
            }

            $openApi->components->addSchema($name, Schema::fromType(new class($factory->object($resource::schema($factory))->toArray()) extends Type
            {
                /**
                 * @param  array<string, mixed>  $schema
                 */
                public function __construct(private readonly array $schema)
                {
                    parent::__construct('object');
                }

                public function serializeAs31(): mixed
                {
                    return $this->schema;
                }

                public function serializeAs32(): mixed
                {
                    return $this->schema;
                }
            }));
        }
    }
}
