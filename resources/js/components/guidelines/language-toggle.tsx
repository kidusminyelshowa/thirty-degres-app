import { useEffect, useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/lib/i18n';
import type { ContentLanguage, ReadingLanguage } from '@/types';

const KEY = 'guidelines.language';

/**
 * Which language guideline pages show, remembered on this device. A
 * language the brand does not use falls back to showing them all.
 */
export function useReadingLanguage(
    languages: ContentLanguage[],
): [ReadingLanguage, (language: ReadingLanguage) => void] {
    const [saved, setSaved] = useState<ReadingLanguage>('all');

    useEffect(() => {
        try {
            const stored = window.localStorage.getItem(KEY);

            if (stored) {
                setSaved(stored);
            }
        } catch {
            // Private windows can refuse storage; the default is fine.
        }
    }, []);

    const language =
        saved === 'all' || languages.some((l) => l.code === saved)
            ? saved
            : 'all';

    return [
        language,
        (next) => {
            setSaved(next);

            try {
                window.localStorage.setItem(KEY, next);
            } catch {
                // As above.
            }
        },
    ];
}

export function LanguageToggle({
    value,
    onChange,
    languages,
}: {
    value: ReadingLanguage;
    onChange: (language: ReadingLanguage) => void;
    languages: ContentLanguage[];
}) {
    const { t } = useTranslation();

    if (languages.length < 2) {
        return null;
    }

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => next && onChange(next)}
            aria-label={t('Language')}
            className="flex-wrap"
        >
            <ToggleGroupItem value="all" className="px-3">
                {t('All')}
            </ToggleGroupItem>
            {languages.map((language) => (
                <ToggleGroupItem
                    key={language.code}
                    value={language.code}
                    lang={language.code}
                    dir={language.dir}
                    className="px-3"
                    title={language.name}
                >
                    {language.native}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
