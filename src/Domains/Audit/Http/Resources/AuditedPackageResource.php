<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Http\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Keystone\Http\Resources\JsonResource;
use RefactorCircus\Keystone\Packages\Package;

/**
 * A package whose history the caller may read, as a front end's per-package
 * History navigation needs it.
 *
 * @mixin Package
 */
final class AuditedPackageResource extends JsonResource
{
    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->description('The package key, as its history and audit source name it.')->required(),
            'label' => $schema->string()->required(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        /** @var Package $package */
        $package = $this->resource;

        return [
            'key' => $package->key,
            'label' => $package->label,
        ];
    }
}
