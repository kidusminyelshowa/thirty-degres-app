import { usePage } from '@inertiajs/react';

export type Translate = (
    text: string,
    replacements?: Record<string, string | number>,
) => string;

/**
 * Translates English source text using the strings the server shares for the
 * current language (lang/{locale}.json), the same files Laravel's __() reads.
 * Text without a translation is shown in English.
 */
export function useTranslation(): { t: Translate; locale: string } {
    const { translations, locale } = usePage().props;

    const t: Translate = (text, replacements = {}) =>
        Object.entries(replacements).reduce(
            (result, [key, value]) =>
                result.replaceAll(`:${key}`, String(value)),
            translations[text] ?? text,
        );

    return { t, locale };
}
