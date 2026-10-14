import { Head, Link, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { kindLabel, roleLabel } from '@/lib/roles';
import { index as members } from '@/routes/workspaces/members';

type Props = {
    memberCount: number | null;
    partnerAccessEndsAt: string | null;
};

export default function ShowWorkspace({
    memberCount,
    partnerAccessEndsAt,
}: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <>
            <Head title={workspace.name} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={workspace.name}
                        description={t(
                            'Brands, files and guidelines will live here.',
                        )}
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
                        <Button asChild>
                            <Link href={members(workspace.slug)}>
                                <Users />
                                {t('Invite and manage people')}
                            </Link>
                        </Button>
                    </section>
                )}
            </div>
        </>
    );
}
