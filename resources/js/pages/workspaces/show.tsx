import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderOpen, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { NewBrandDialog } from '@/components/new-brand-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { brandRoleLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { kindLabel, roleLabel } from '@/lib/roles';
import { approval } from '@/routes/workspaces/settings';
import { show as showBrand } from '@/routes/workspaces/brands';
import { index as members } from '@/routes/workspaces/members';
import type { BrandSummary } from '@/types';

type Props = {
    memberCount: number | null;
    partnerAccessEndsAt: string | null;
    brands: (BrandSummary & { files: number })[];
    canCreateBrand: boolean;
    selfApproval: { enabled: boolean } | null;
};

export default function ShowWorkspace({
    memberCount,
    partnerAccessEndsAt,
    brands,
    canCreateBrand,
    selfApproval,
}: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const topLevel = brands.filter(
        (brand) =>
            brand.parentId === null ||
            !brands.some((parent) => parent.id === brand.parentId),
    );

    return (
        <>
            <Head title={workspace.name} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={workspace.name}
                        description={t('Your brands and their files.')}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary">
                            {roleLabel(t, workspace.role)}
                        </Badge>
                        {workspace.kind === 'partner' && (
                            <Badge variant="outline">
                                {kindLabel(t, workspace.kind)}
                            </Badge>
                        )}
                    </div>
                </div>

                {partnerAccessEndsAt && (
                    <p className="text-sm text-muted-foreground">
                        {t('Your access ends on :date.', {
                            date: partnerAccessEndsAt,
                        })}
                    </p>
                )}

                <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-medium">{t('Brands')}</h2>
                        {canCreateBrand && <NewBrandDialog />}
                    </div>

                    {brands.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                            {canCreateBrand
                                ? t(
                                      'Create your first brand, then upload its logo pack.',
                                  )
                                : t(
                                      'You have not been given access to a brand yet. Ask an Admin to add you.',
                                  )}
                        </div>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {topLevel.map((brand) => (
                                <li
                                    key={brand.id}
                                    className="flex flex-col rounded-xl border"
                                >
                                    <Link
                                        href={showBrand([
                                            workspace.slug,
                                            brand.id,
                                        ])}
                                        className="flex flex-col gap-1 p-4 hover:bg-accent/50"
                                    >
                                        <span className="flex items-center gap-2 font-medium">
                                            <FolderOpen
                                                className="size-4 shrink-0 text-primary"
                                                aria-hidden
                                            />
                                            <span className="truncate">
                                                {brand.name}
                                            </span>
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {t('Files: :count', {
                                                count: brand.files,
                                            })}
                                            {' · '}
                                            {brandRoleLabel(t, brand.role)}
                                        </span>
                                    </Link>
                                    {brands.some(
                                        (sub) => sub.parentId === brand.id,
                                    ) && (
                                        <ul className="flex flex-wrap gap-1.5 border-t px-4 py-3">
                                            {brands
                                                .filter(
                                                    (sub) =>
                                                        sub.parentId ===
                                                        brand.id,
                                                )
                                                .map((sub) => (
                                                    <li key={sub.id}>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={showBrand(
                                                                    [
                                                                        workspace.slug,
                                                                        sub.id,
                                                                    ],
                                                                )}
                                                            >
                                                                {sub.name}
                                                                <span className="text-muted-foreground">
                                                                    {sub.files}
                                                                </span>
                                                            </Link>
                                                        </Button>
                                                    </li>
                                                ))}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {workspace.canManageMembers && (
                    <section className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-medium">{t('Your team')}</h2>
                            <p className="text-sm text-muted-foreground">
                                {t('People in this workspace: :count', {
                                    count: memberCount ?? 0,
                                })}
                            </p>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={members(workspace.slug)}>
                                <Users />
                                {t('Invite and manage people')}
                            </Link>
                        </Button>
                    </section>
                )}

                {selfApproval && (
                    <section className="flex flex-col gap-2 rounded-xl border p-4">
                        <h2 className="font-medium">{t('Approvals')}</h2>
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="allow-self-approval"
                                checked={selfApproval.enabled}
                                onCheckedChange={(checked) =>
                                    router.patch(
                                        approval.url(workspace.slug),
                                        {
                                            allow_self_approval:
                                                checked === true,
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="allow-self-approval">
                                    {t('Let people approve their own uploads')}
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Only for a workspace with one person in it. Normally a second Brand manager approves every file, and the activity log marks any self-approval.',
                                    )}
                                </p>
                            </div>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
