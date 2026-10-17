import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update } from '@/routes/language';

/**
 * Switches the app's own screens between its interface languages. Each
 * language is named in itself, so people can find theirs whatever the
 * screen is showing now.
 */
export function useInterfaceLanguage() {
    const { interfaceLanguages, locale } = usePage().props;

    const change = (code: string) => {
        if (code !== locale) {
            router.put(
                update.url(),
                { locale: code },
                { preserveScroll: true },
            );
        }
    };

    return { languages: interfaceLanguages, current: locale, change };
}

export default function InterfaceLanguageSwitch({
    className,
}: {
    className?: string;
}) {
    const { t } = useTranslation();
    const { languages, current, change } = useInterfaceLanguage();

    return (
        <div
            role="group"
            aria-label={t('Language')}
            className={cn(
                'inline-flex items-center gap-1 rounded-lg bg-muted p-1',
                className,
            )}
        >
            <Languages
                className="mx-1 size-4 text-muted-foreground"
                aria-hidden
            />
            {languages.map((language) => (
                <button
                    key={language.code}
                    type="button"
                    lang={language.code}
                    aria-pressed={current === language.code}
                    onClick={() => change(language.code)}
                    className={cn(
                        'rounded-md px-3 py-1 text-sm transition-colors',
                        current === language.code
                            ? 'bg-background shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {language.native}
                </button>
            ))}
        </div>
    );
}
