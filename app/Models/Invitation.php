<?php

namespace App\Models;

use App\Enums\BrandRoleName;
use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An emailed invitation to join a workspace. Only a hash of the link's
 * secret is stored, so reading the database cannot rebuild working links.
 *
 * @property string $id
 * @property string $email
 * @property WorkspaceRole $role
 * @property MembershipKind $kind
 * @property string|null $partner_organisation
 * @property CarbonImmutable|null $access_ends_at
 * @property string|null $brand_id
 * @property BrandRoleName|null $brand_role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $inviter
 * @property-read Brand|null $brand
 * @property-read Workspace $workspace
 */
#[Fillable(['email', 'role', 'kind', 'partner_organisation', 'access_ends_at', 'brand_id', 'brand_role', 'token_hash', 'invited_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use BelongsToWorkspace, HasUuids;

    public const DAYS_VALID = 7;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * The one brand a partner is invited onto, if any.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'kind' => MembershipKind::class,
            'access_ends_at' => 'datetime',
            'brand_role' => BrandRoleName::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
