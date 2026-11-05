import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import InterfaceLanguageSwitch from '@/components/interface-language-switch';
import { useTranslation } from '@/lib/i18n';

/**
 * Pages opened from a share link: no sidebar and nothing about the
 * workspace beyond what the link shares.
 */
export default function SharedLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 md:px-6 print:hidden">
                <span className="flex items-baseline gap-2 text-sm text-muted-foreground">
                    <AppLogo />
                    <span>{t('Shared link, read only')}</span>
                </span>
                <InterfaceLanguageSwitch />
            </header>
            <main className="flex flex-1 flex-col">{children}</main>
        </div>
    );
}
