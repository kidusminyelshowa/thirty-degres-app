import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { brandRoleLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { roleLabel } from '@/lib/roles';
import { dashboard, login, logout, register } from '@/routes';
import { accept } from '@/routes/invitations';
import type { MembershipKind, WorkspaceRole } from '@/types';

type Props = {
    invitation: {
        token: string;
        workspaceName: string;
        email: string;
        role: WorkspaceRole;
        kind: MembershipKind;
        brandName: string | null;
        brandRole: 'editor' | 'viewer' | null;
        invitedBy: string | null;
        emailMatches: boolean;
    } | null;
};

export default function ShowInvitation({ invitation }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    if (!invitation) {
        return (
            <>
                <Head title={t('Invitation')} />
                <div className="space-y-4 text-center">
                    <p className="text-muted-foreground">
                        {t(
                            'This invitation link has expired or was already used. Ask the person who invited you to send a new one.',
                        )}
                    </p>
                    <TextLink href={auth.user ? dashboard() : login()}>
                        {auth.user ? t('Go to your workspace') : t('Log in')}
                    </TextLink>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={t('Invitation')} />
            <div className="space-y-6">
                <p className="text-center">
                    {invitation.invitedBy
                        ? t(
                              ':inviter invited you to join :workspace as :role.',
                              {
                                  inviter: invitation.invitedBy,
                                  workspace: invitation.workspaceName,
                                  role: roleLabel(t, invitation.role),
                              },
                          )
                        : t('You are invited to join :workspace as :role.', {
                              workspace: invitation.workspaceName,
                              role: roleLabel(t, invitation.role),
                          })}
                </p>

                {invitation.brandName && invitation.brandRole && (
                    <p className="text-center text-sm text-muted-foreground">
                        {t(
                            'You will see the brand :brand as :role, and nothing else in :workspace.',
                            {
                                brand: invitation.brandName,
                                role: brandRoleLabel(t, invitation.brandRole),
                                workspace: invitation.workspaceName,
                            },
                        )}
                    </p>
                )}

                {!auth.user && (
                    <div className="grid gap-3">
                        <Button asChild>
                            <Link href={register()}>
                                {t('Create an account')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={login()}>
                                {t('I already have an account')}
                            </Link>
                        </Button>
                        <p className="text-center text-sm text-muted-foreground">
                            {t('Use :email, the address this was sent to.', {
                                email: invitation.email,
                            })}
                        </p>
                    </div>
                )}

                {auth.user && invitation.emailMatches && (
                    <Form {...accept.form(invitation.token)}>
                        {({ processing, errors }) => (
                            <>
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {t('Join :workspace', {
                                        workspace: invitation.workspaceName,
                                    })}
                                </Button>
                                <InputError message={errors.invitation} />
                            </>
                        )}
                    </Form>
                )}

                {auth.user && !invitation.emailMatches && (
                    <div className="space-y-3 text-center text-sm">
                        <p className="text-muted-foreground">
                            {t(
                                'This invitation was sent to :email, but you are signed in as :current.',
                                {
                                    email: invitation.email,
                                    current: auth.user.email,
                                },
                            )}
                        </p>
                        <TextLink href={logout()} method="post" as="button">
                            {t('Log out and use the right account')}
                        </TextLink>
                    </div>
                )}
            </div>
        </>
    );
}

ShowInvitation.layout = {
    title: 'You are invited',
    description: '',
};
