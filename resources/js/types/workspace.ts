export type WorkspaceRole = 'owner' | 'admin' | 'member';

export type MembershipKind = 'staff' | 'partner';

export type WorkspaceSummary = {
    name: string;
    slug: string;
    role: WorkspaceRole;
};

export type CurrentWorkspace = WorkspaceSummary & {
    kind: MembershipKind;
    canManageMembers: boolean;
};
