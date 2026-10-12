<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;
use RefactorCircus\Keystone\Auth\Authorizer;
use RefactorCircus\Keystone\Http\Resources\JsonResource;
use RefactorCircus\Keystone\Packages\PackageRegistry;
use WeakMap;

/**
 * IP addresses and user agents are personal data: they are shown only to
 * people the policy's `viewPersonalData` allows. It is asked once per user and
 * source per request, not once per entry, so a page of entries checks it a
 * handful of times at most.
 *
 * @mixin AuditEntryModel
 */
final class AuditEntryResource extends JsonResource
{
    /**
     * `viewPersonalData` answers by request, then by user and source: held
     * only as long as the request is.
     *
     * @var WeakMap<Request, array<string, bool>>|null
     */
    private static ?WeakMap $personal = null;

    /**
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'source' => $schema->string()->description('The package key, or app for the application\'s own.')->required(),
            'action' => $schema->string()->required(),
            'actor' => $schema->object([
                'type' => $schema->string()->nullable()->required(),
                'id' => $schema->string()->required(),
                'label' => $schema->string()->nullable()->required(),
            ])->nullable()->required(),
            'subject' => $schema->object([
                'type' => $schema->string()->nullable()->required(),
                'id' => $schema->string()->nullable()->required(),
                'label' => $schema->string()->nullable()->required(),
            ])->nullable()->required(),
            'scope' => $schema->object([
                'type' => $schema->string()->required(),
                'id' => $schema->string()->nullable()->required(),
            ])->nullable()->required(),
            'surface' => $schema->string()->required(),
            'ip' => $schema->string()->nullable()->description('Only for people who may read the whole log.'),
            'user_agent' => $schema->string()->nullable()->description('Only for people who may read the whole log.'),
            'changes' => $schema->object()->description('Field changes, as field => [old, new].')->required(),
            'context' => $schema->object()->required(),
            'hash' => $schema->string()->required(),
            'created_at' => $schema->string()->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditEntryModel $entry */
        $entry = $this->resource;
        $personal = $this->personal($request, $entry);

        return [
            'id' => $entry->id,
            'source' => $entry->source,
            'action' => $entry->action,
            'actor' => $entry->actor_id === null ? null : [
                'type' => $entry->actor_type,
                'id' => $entry->actor_id,
                'label' => $entry->actor_label,
            ],
            'subject' => $entry->subject_type === null && $entry->subject_label === null ? null : [
                'type' => $entry->subject_type,
                'id' => $entry->subject_id,
                'label' => $entry->subject_label,
            ],
            'scope' => $entry->scope_type === null ? null : [
                'type' => $entry->scope_type,
                'id' => $entry->scope_id,
            ],
            'surface' => $entry->surface,
            'ip' => $this->when($personal, $entry->ip),
            'user_agent' => $this->when($personal, $entry->user_agent),
            'changes' => $entry->changes ?? (object) [],
            'context' => $entry->context ?? (object) [],
            'hash' => $entry->hash,
            'created_at' => $entry->created_at->toIso8601String(),
        ];
    }

    private function personal(Request $request, AuditEntryModel $entry): bool
    {
        self::$personal ??= new WeakMap;

        $user = $request->user();
        $user = $user instanceof Authenticatable ? $user : null;
        $key = ($user === null ? '' : $user::class.':'.$this->identifier($user)).'|'.$entry->source;
        $answers = self::$personal[$request] ?? [];

        if (! array_key_exists($key, $answers)) {
            $answers[$key] = Authorizer::for(app(PackageRegistry::class)->get('keen'))->can($user, 'viewPersonalData', $entry);
            self::$personal[$request] = $answers;
        }

        return $answers[$key];
    }

    private function identifier(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : (string) spl_object_id($user);
    }
}
