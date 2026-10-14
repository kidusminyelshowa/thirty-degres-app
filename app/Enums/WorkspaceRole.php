<?php

namespace App\Enums;

/**
 * A person's role in a workspace (docs/roles-and-permissions.md).
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Member => __('Member'),
        };
    }

    /**
     * Owners and Admins run the workspace: invite people, give roles, create brands.
     */
    public function managesWorkspace(): bool
    {
        return $this !== self::Member;
    }
}
