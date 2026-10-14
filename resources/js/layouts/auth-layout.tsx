import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { useTranslation } from '@/lib/i18n';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <AuthLayoutTemplate title={t(title)} description={t(description)}>
            {children}
        </AuthLayoutTemplate>
    );
}
