<?php

namespace App\Assets;

use App\Enums\BrandRoleName;
use App\Models\Brand;
use App\Models\BrandRole;
use App\Models\Membership;
use App\Tenancy\Tenancy;

/**
 * Works out the signed-in person's role on each brand of the current
 * workspace (docs/roles-and-permissions.md):
 *
 * - Owners and Admins who are staff are Brand manager on every brand.
 * - Everyone else has the role they were given on a brand, which also
 *   applies to its sub-brands. A sub-brand can add to that, never take away.
 * - Partners are never Brand manager, whatever a row says.
 */
class BrandAccess
{
    /** @var array<string, BrandRoleName>|null */
    private ?array $roles = null;

    private ?int $for = null;

    public function __construct(private Tenancy $tenancy) {}

    public function roleOn(Brand|string $brand): ?BrandRoleName
    {
        $id = $brand instanceof Brand ? $brand->id : $brand;

        return $this->roles()[$id] ?? null;
    }

    /**
     * Every brand the person can see, with their role on it.
     *
     * @return array<string, BrandRoleName>
     */
    public function roles(): array
    {
        $membership = $this->tenancy->membership();

        if ($membership === null || ! $membership->isActive()) {
            return [];
        }

        if ($this->roles !== null && $this->for === spl_object_id($membership)) {
            return $this->roles;
        }

        $this->for = spl_object_id($membership);

        return $this->roles = $this->resolve($membership);
    }

    /**
     * Brand ids where the person has at least this role.
     *
     * @return list<string>
     */
    public function brandIdsWith(BrandRoleName $role): array
    {
        return array_keys(array_filter($this->roles(), fn (BrandRoleName $held) => $held->atLeast($role)));
    }

    /**
     * Forget what was worked out, after roles change in this request.
     */
    public function flush(): void
    {
        $this->roles = null;
        $this->for = null;
    }

    /**
     * @return array<string, BrandRoleName>
     */
    private function resolve(Membership $membership): array
    {
        /** @var array<string, string|null> $parents */
        $parents = Brand::query()->pluck('parent_id', 'id')->all();

        if ($membership->managesWorkspace()) {
            return array_fill_keys(array_keys($parents), BrandRoleName::Manager);
        }

        $direct = BrandRole::query()
            ->where('membership_id', $membership->id)
            ->get()
            ->mapWithKeys(fn (BrandRole $role) => [$role->brand_id => $role->role])
            ->all();

        $roles = [];

        foreach ($parents as $brandId => $parentId) {
            $role = BrandRoleName::strongest(
                $direct[$brandId] ?? null,
                $parentId !== null ? ($direct[$parentId] ?? null) : null,
            );

            if ($role === BrandRoleName::Manager && $membership->isPartner()) {
                $role = BrandRoleName::Editor;
            }

            if ($role !== null) {
                $roles[$brandId] = $role;
            }
        }

        return $roles;
    }
}
