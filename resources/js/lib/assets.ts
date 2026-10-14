import type { Translate } from '@/lib/i18n';
import type { AssetKind, AssetStatus, BrandRoleName } from '@/types';

export const assetKinds: AssetKind[] = [
    'logo',
    'font',
    'photo',
    'document',
    'other',
];

export function kindLabel(t: Translate, kind: AssetKind): string {
    return {
        logo: t('Logo'),
        font: t('Font'),
        photo: t('Photo'),
        document: t('Document'),
        other: t('Other'),
    }[kind];
}

export function statusLabel(t: Translate, status: AssetStatus): string {
    return {
        draft: t('Draft'),
        approved: t('Approved'),
        archived: t('Archived'),
    }[status];
}

export function brandRoleLabel(t: Translate, role: BrandRoleName): string {
    return {
        manager: t('Brand manager'),
        editor: t('Editor'),
        viewer: t('Viewer'),
    }[role];
}

export function brandRoleDescription(
    t: Translate,
    role: BrandRoleName,
): string {
    return {
        manager: t('Approves files and decides who works on the brand'),
        editor: t('Uploads files and edits drafts'),
        viewer: t('Sees and downloads approved files'),
    }[role];
}

/**
 * File sizes in the units people know from their phones: KB, MB, GB.
 */
export function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size < 10 ? size.toFixed(1) : Math.round(size)} ${units[unit]}`;
}
