<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Host;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A host's organization, as strictly as a real host has it: no morph alias
 * (its morph class is its class name), a ULID key, and routed by its slug,
 * which is what the screens that show it pass as the record (D-019).
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 */
final class OrganizationModel extends Model
{
    use HasUlids;

    protected $table = 'organizations';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
