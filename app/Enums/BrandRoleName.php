<?php

namespace App\Enums;

/**
 * A person's role on one brand, inherited by its sub-brands
 * (docs/roles-and-permissions.md). Owners and Admins count as Brand manager
 * on every brand.
 */
enum BrandRoleName: string
{
    case Manager = 'manager';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => __('Brand manager'),
            self::Editor => __('Editor'),
            self::Viewer => __('Viewer'),
        };
    }

    /**
     * Higher means more access, for picking the strongest of several roles.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Manager => 3,
            self::Editor => 2,
            self::Viewer => 1,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public static function strongest(?self ...$roles): ?self
    {
        $best = null;

        foreach ($roles as $role) {
            if ($role !== null && ($best === null || $role->rank() > $best->rank())) {
                $best = $role;
            }
        }

        return $best;
    }
}
