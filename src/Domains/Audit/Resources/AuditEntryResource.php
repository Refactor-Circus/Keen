<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RefactorCircus\Foundation\Auth\Authorizer;
use RefactorCircus\Foundation\Packages\PackageRegistry;
use RefactorCircus\Keen\Domains\Audit\Models\AuditEntryModel;

/**
 * IP addresses and user agents are personal data: they are shown only to
 * people the policy's `viewPersonalData` allows.
 *
 * @mixin AuditEntryModel
 */
final class AuditEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditEntryModel $entry */
        $entry = $this->resource;
        $personal = Authorizer::for(app(PackageRegistry::class)->get('keen'))->can($request->user(), 'viewPersonalData', $entry);

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
}
