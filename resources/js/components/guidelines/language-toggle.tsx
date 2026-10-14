import { useEffect, useState } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/lib/i18n';
import type { ReadingLanguage } from '@/types';

const KEY = 'guidelines.language';

/**
 * Which language guideline pages show, remembered on this device.
 */
export function useReadingLanguage(): [
    ReadingLanguage,
    (language: ReadingLanguage) => void,
] {
    const [language, setLanguage] = useState<ReadingLanguage>('both');

    useEffect(() => {
        const saved = window.localStorage.getItem(KEY);

        if (saved === 'en' || saved === 'am' || saved === 'both') {
            setLanguage(saved);
        }
    }, []);

    return [
        language,
        (next) => {
            setLanguage(next);
            window.localStorage.setItem(KEY, next);
        },
    ];
}

export function LanguageToggle({
    value,
    onChange,
}: {
    value: ReadingLanguage;
    onChange: (language: ReadingLanguage) => void;
}) {
    const { t } = useTranslation();

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => next && onChange(next as ReadingLanguage)}
            aria-label={t('Language')}
        >
            <ToggleGroupItem value="both" className="px-3">
                {t('Both')}
            </ToggleGroupItem>
            <ToggleGroupItem value="am" lang="am" className="px-3">
                አማርኛ
            </ToggleGroupItem>
            <ToggleGroupItem value="en" lang="en" className="px-3">
                English
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
