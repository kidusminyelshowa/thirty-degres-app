<?php

namespace App\Assets;

use App\Enums\AssetStatus;
use App\Enums\BrandRoleName;
use App\Models\Asset;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limits a query on files to the ones the signed-in person may see, using the
 * same rules as AssetPolicy::view:
 *
 * - Viewers see approved files.
 * - Editors also see drafts; partner Editors only the drafts they uploaded.
 * - Brand managers see everything, archived files only when asked for.
 */
class VisibleAssets
{
    public function __construct(private BrandAccess $access, private Tenancy $tenancy) {}

    /**
     * @param  Builder<Asset>  $query
     * @return Builder<Asset>
     */
    public function apply(Builder $query, bool $includeArchived = false): Builder
    {
        $membership = $this->tenancy->membership();
        $roles = $this->access->roles();

        if ($membership === null || $roles === []) {
            return $query->whereRaw('false');
        }

        $byRole = [];

        foreach ($roles as $brandId => $role) {
            $byRole[$role->value][] = $brandId;
        }

        return $query->where(function (Builder $query) use ($byRole, $membership, $includeArchived) {
            $query->whereRaw('false');

            if (isset($byRole[BrandRoleName::Manager->value])) {
                $query->orWhere(fn (Builder $query) => $query
                    ->whereIn('brand_id', $byRole[BrandRoleName::Manager->value])
                    ->when(! $includeArchived, fn (Builder $query) => $query->where('status', '!=', AssetStatus::Archived)));
            }

            if (isset($byRole[BrandRoleName::Editor->value])) {
                $query->orWhere(fn (Builder $query) => $query
                    ->whereIn('brand_id', $byRole[BrandRoleName::Editor->value])
                    ->where(fn (Builder $query) => $query
                        ->where('status', AssetStatus::Approved)
                        ->orWhere(fn (Builder $query) => $query
                            ->where('status', AssetStatus::Draft)
                            ->when($membership->isPartner(), fn (Builder $query) => $query->where('uploaded_by', $membership->user_id)))));
            }

            if (isset($byRole[BrandRoleName::Viewer->value])) {
                $query->orWhere(fn (Builder $query) => $query
                    ->whereIn('brand_id', $byRole[BrandRoleName::Viewer->value])
                    ->where('status', AssetStatus::Approved));
            }
        });
    }
}
