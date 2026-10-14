<?php

namespace App\Models;

use App\Enums\MembershipKind;
use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's place in a workspace: their role, and whether they are staff
 * or an outside partner whose access ends on a set date.
 *
 * @property string $id
 * @property int $user_id
 * @property WorkspaceRole $role
 * @property MembershipKind $kind
 * @property string|null $partner_organisation
 * @property CarbonImmutable|null $access_ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Workspace $workspace
 */
#[Fillable(['user_id', 'role', 'kind', 'partner_organisation', 'access_ends_at'])]
class Membership extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->access_ends_at === null || $this->access_ends_at->isFuture();
    }

    public function isPartner(): bool
    {
        return $this->kind === MembershipKind::Partner;
    }

    /**
     * Owners and Admins who are staff. Partners never manage a workspace.
     */
    public function managesWorkspace(): bool
    {
        return ! $this->isPartner() && $this->role->managesWorkspace() && $this->isActive();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('access_ends_at')
            ->orWhere('access_ends_at', '>', now()));
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
        ];
    }
}
