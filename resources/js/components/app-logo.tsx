import { usePage } from '@inertiajs/react';

/**
 * The product name as plain text. It stands in for a logo until the
 * Aluma identity is locked (THI-45, 20 November).
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <span className="truncate text-base font-semibold tracking-tight">
            {name}
        </span>
    );
}
