<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One client company's private space. Postgres only shows a workspace to
 * its members (and, by name only, to someone holding an invitation link).
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property bool $allow_self_approval
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Workspace extends Model
{
    use HasUuids;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_self_approval' => 'boolean',
        ];
    }
}
