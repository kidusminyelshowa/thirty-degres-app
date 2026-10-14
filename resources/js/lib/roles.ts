import type { Translate } from '@/lib/i18n';
import type { MembershipKind, WorkspaceRole } from '@/types';

export function roleLabel(t: Translate, role: WorkspaceRole): string {
    return {
        owner: t('Owner'),
        admin: t('Admin'),
        member: t('Member'),
    }[role];
}

export function roleDescription(t: Translate, role: WorkspaceRole): string {
    return {
        owner: t('Everything, including billing and deleting the workspace'),
        admin: t('Invites people, gives roles and creates brands'),
        member: t('Works on the brands they are given'),
    }[role];
}

export function kindLabel(t: Translate, kind: MembershipKind): string {
    return { staff: t('Staff'), partner: t('Partner') }[kind];
}
