<?php

namespace App\Models;

use App\Enums\BrandRoleName;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's role on one brand. It also applies to the brand's sub-brands.
 *
 * @property string $id
 * @property string $brand_id
 * @property string $membership_id
 * @property BrandRoleName $role
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand $brand
 * @property-read Membership $membership
 */
#[Fillable(['brand_id', 'membership_id', 'role'])]
class BrandRole extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => BrandRoleName::class,
        ];
    }
}
