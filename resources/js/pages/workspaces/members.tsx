import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { brandRoleLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { kindLabel, roleDescription, roleLabel } from '@/lib/roles';
import {
    destroy as cancelInvitation,
    store as sendInvitation,
} from '@/routes/workspaces/invitations';
import {
    destroy as removeMember,
    update as updateMember,
} from '@/routes/workspaces/members';
import type { MembershipKind, WorkspaceRole } from '@/types';

type Member = {
    id: string;
    name: string;
    email: string;
    role: WorkspaceRole;
    kind: MembershipKind;
    partnerOrganisation: string | null;
    accessEndsAt: string | null;
    isActive: boolean;
    isYou: boolean;
    canRemove: boolean;
};

type PendingInvitation = {
    id: string;
    email: string;
    role: WorkspaceRole;
    kind: MembershipKind;
    partnerOrganisation: string | null;
    brandName: string | null;
    brandRole: GuestBrandRole | null;
    expiresAt: string;
    invitedBy: string | null;
};

type GuestBrandRole = 'editor' | 'viewer';

type BrandOption = { id: string; name: string };

type Props = {
    members: Member[];
    invitations: PendingInvitation[];
    assignableRoles: WorkspaceRole[];
    defaultPartnerAccessEndsAt: string;
    brands: BrandOption[];
};

export default function Members({
    members,
    invitations,
    assignableRoles,
    defaultPartnerAccessEndsAt,
    brands,
}: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <>
            <Head title={t('People')} />

            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={t('People')}
                    description={t(
                        'Everyone who can see :workspace. Partners from agencies and printers only keep access until their end date.',
                        { workspace: workspace.name },
                    )}
                />

                <InviteForm
                    slug={workspace.slug}
                    assignableRoles={assignableRoles}
                    defaultPartnerAccessEndsAt={defaultPartnerAccessEndsAt}
                    brands={brands}
                />

                <section className="space-y-3">
                    <h2 className="font-medium">{t('Members')}</h2>
                    <ul className="divide-y rounded-xl border">
                        {members.map((member) => (
                            <MemberRow
                                key={member.id}
                                member={member}
                                slug={workspace.slug}
                                actorRole={workspace.role}
                                assignableRoles={assignableRoles}
                            />
                        ))}
                    </ul>
                </section>

                {invitations.length > 0 && (
                    <section className="space-y-3">
                        <h2 className="font-medium">
                            {t('Waiting to accept')}
                        </h2>
                        <ul className="divide-y rounded-xl border">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate">
                                            {invitation.email}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {roleLabel(t, invitation.role)}
                                            {invitation.kind === 'partner' &&
                                                ` · ${kindLabel(t, invitation.kind)}, ${invitation.partnerOrganisation}`}
                                            {invitation.brandName &&
                                                invitation.brandRole &&
                                                ` · ${t(':role on :brand', {
                                                    role: brandRoleLabel(
                                                        t,
                                                        invitation.brandRole,
                                                    ),
                                                    brand: invitation.brandName,
                                                })}`}
                                            {' · '}
                                            {t('link works until :date', {
                                                date: invitation.expiresAt,
                                            })}
                                        </p>
                                    </div>
                                    <Form
                                        {...cancelInvitation.form([
                                            workspace.slug,
                                            invitation.id,
                                        ])}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {t('Cancel invitation')}
                                            </Button>
                                        )}
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function InviteForm({
    slug,
    assignableRoles,
    defaultPartnerAccessEndsAt,
    brands,
}: {
    slug: string;
    assignableRoles: WorkspaceRole[];
    defaultPartnerAccessEndsAt: string;
    brands: BrandOption[];
}) {
    const { t } = useTranslation();
    const [kind, setKind] = useState<MembershipKind>('staff');

    return (
        <section className="rounded-xl border p-4">
            <h2 className="mb-4 font-medium">{t('Invite someone')}</h2>
            <Form
                {...sendInvitation.form(slug)}
                options={{ preserveScroll: true }}
                resetOnSuccess={['email', 'partner_organisation']}
                className="grid gap-4 sm:grid-cols-2"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="invite-email">
                                {t('Email address')}
                            </Label>
                            <Input
                                id="invite-email"
                                type="email"
                                name="email"
                                required
                                placeholder="name@company.et"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="invite-kind">
                                {t('They work for')}
                            </Label>
                            <Select
                                name="kind"
                                value={kind}
                                onValueChange={(value) =>
                                    setKind(value as MembershipKind)
                                }
                            >
                                <SelectTrigger id="invite-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="staff">
                                        {t('This company (staff)')}
                                    </SelectItem>
                                    <SelectItem value="partner">
                                        {t('Another organisation (partner)')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.kind} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="invite-role">{t('Role')}</Label>
                            {kind === 'partner' ? (
                                <>
                                    <input
                                        type="hidden"
                                        name="role"
                                        value="member"
                                    />
                                    <p className="py-2 text-sm text-muted-foreground">
                                        {t('Partners are always Members.')}
                                    </p>
                                </>
                            ) : (
                                <Select name="role" defaultValue="member">
                                    <SelectTrigger id="invite-role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignableRoles.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                <span className="flex flex-col items-start">
                                                    <span>
                                                        {roleLabel(t, role)}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {roleDescription(
                                                            t,
                                                            role,
                                                        )}
                                                    </span>
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            <InputError message={errors.role} />
                        </div>

                        {kind === 'partner' && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-organisation">
                                        {t('Their organisation')}
                                    </Label>
                                    <Input
                                        id="invite-organisation"
                                        name="partner_organisation"
                                        required
                                        placeholder={t(
                                            'e.g. Addis Print House',
                                        )}
                                    />
                                    <InputError
                                        message={errors.partner_organisation}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-ends">
                                        {t('Access ends on')}
                                    </Label>
                                    <Input
                                        id="invite-ends"
                                        type="date"
                                        name="access_ends_at"
                                        required
                                        defaultValue={
                                            defaultPartnerAccessEndsAt
                                        }
                                    />
                                    <InputError
                                        message={errors.access_ends_at}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-brand">
                                        {t('Brand they can see')}
                                    </Label>
                                    {brands.length > 0 ? (
                                        <Select
                                            name="brand_id"
                                            defaultValue={brands[0].id}
                                        >
                                            <SelectTrigger id="invite-brand">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {brands.map((brand) => (
                                                    <SelectItem
                                                        key={brand.id}
                                                        value={brand.id}
                                                    >
                                                        {brand.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <p className="py-2 text-sm text-muted-foreground">
                                            {t(
                                                'Create a brand first. Partners only ever see the brand they are invited to.',
                                            )}
                                        </p>
                                    )}
                                    <InputError message={errors.brand_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-brand-role">
                                        {t('On that brand they can')}
                                    </Label>
                                    <Select
                                        name="brand_role"
                                        defaultValue="viewer"
                                    >
                                        <SelectTrigger id="invite-brand-role">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="viewer">
                                                <span className="flex flex-col items-start">
                                                    <span>
                                                        {brandRoleLabel(
                                                            t,
                                                            'viewer',
                                                        )}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {t(
                                                            'See and download approved files and guidelines',
                                                        )}
                                                    </span>
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="editor">
                                                <span className="flex flex-col items-start">
                                                    <span>
                                                        {brandRoleLabel(
                                                            t,
                                                            'editor',
                                                        )}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {t(
                                                            'Also upload drafts and write guideline drafts',
                                                        )}
                                                    </span>
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.brand_role} />
                                </div>
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    {t(
                                        'Partners see only this brand and its sub-brands, only approved files plus their own drafts, and never the people list or settings.',
                                    )}
                                </p>
                            </>
                        )}

                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Send invitation')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function MemberRow({
    member,
    slug,
    actorRole,
    assignableRoles,
}: {
    member: Member;
    slug: string;
    actorRole: WorkspaceRole;
    assignableRoles: WorkspaceRole[];
}) {
    const { t } = useTranslation();
    const canChangeRole =
        !member.isYou &&
        member.kind === 'staff' &&
        (actorRole === 'owner' || member.role !== 'owner');

    const changeRole = (role: string) =>
        router.patch(
            updateMember.url([slug, member.id]),
            { role },
            { preserveScroll: true },
        );

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="min-w-0">
                <p className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">{member.name}</span>
                    {member.isYou && (
                        <Badge variant="secondary">{t('You')}</Badge>
                    )}
                    {member.kind === 'partner' && (
                        <Badge variant="outline">
                            {kindLabel(t, member.kind)} ·{' '}
                            {member.partnerOrganisation}
                        </Badge>
                    )}
                </p>
                <p className="truncate text-sm text-muted-foreground">
                    {member.email}
                    {member.accessEndsAt &&
                        ` · ${
                            member.isActive
                                ? t('access ends :date', {
                                      date: member.accessEndsAt,
                                  })
                                : t('access ended :date', {
                                      date: member.accessEndsAt,
                                  })
                        }`}
                </p>
            </div>

            <div className="flex items-center gap-2">
                {canChangeRole ? (
                    <Select value={member.role} onValueChange={changeRole}>
                        <SelectTrigger
                            className="w-32"
                            aria-label={t('Role for :name', {
                                name: member.name,
                            })}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {assignableRoles.map((role) => (
                                <SelectItem key={role} value={role}>
                                    {roleLabel(t, role)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ) : (
                    <span className="w-32 px-3 text-sm">
                        {roleLabel(t, member.role)}
                    </span>
                )}

                {member.canRemove && (
                    <Form
                        {...removeMember.form([slug, member.id])}
                        options={{ preserveScroll: true }}
                        onBefore={() =>
                            window.confirm(
                                t('Remove :name from this workspace?', {
                                    name: member.name,
                                }),
                            )
                        }
                    >
                        {({ processing }) => (
                            <Button
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                            >
                                {t('Remove')}
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </li>
    );
}
