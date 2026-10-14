<?php

namespace App\Enums;

/**
 * Staff belong to the company; partners (agencies, printers, distributors)
 * get narrower, time-boxed access.
 */
enum MembershipKind: string
{
    case Staff = 'staff';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('Staff'),
            self::Partner => __('Partner'),
        };
    }
}
