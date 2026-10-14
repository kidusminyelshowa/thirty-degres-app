<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brand a company manages, e.g. "telebirr", or a sub-brand under it,
 * e.g. "telebirr Merchant". Sub-brands are one level deep.
 *
 * @property string $id
 * @property string|null $parent_id
 * @property string $name
 * @property list<string> $guideline_languages
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Brand|null $parent
 */
#[Fillable(['name', 'parent_id'])]
class Brand extends Model
{
    use BelongsToWorkspace, HasUuids;

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'parent_id');
    }

    /**
     * @return HasMany<Brand, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Brand::class, 'parent_id');
    }

    /**
     * @return HasMany<BrandRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(BrandRole::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function isSubBrand(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guideline_languages' => 'array',
        ];
    }
}
