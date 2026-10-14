import { useEffect } from 'react';
import type { Translate } from '@/lib/i18n';
import type {
    Bilingual,
    FontSpec,
    GuidelineAsset,
    GuidelineBlock,
    GuidelineBlockType,
    GuidelineTemplate,
    ReadingLanguage,
} from '@/types';

export const blockTypes: GuidelineBlockType[] = [
    'heading',
    'text',
    'logos',
    'colours',
    'type_pairing',
    'dos_donts',
];

export const templates: GuidelineTemplate[] = [
    'logo',
    'colour',
    'type',
    'voice',
    'blank',
];

export function blockLabel(t: Translate, type: GuidelineBlockType): string {
    return {
        heading: t('Heading'),
        text: t('Text'),
        colours: t('Colours'),
        type_pairing: t('Type pairing'),
        logos: t('Logos and lockups'),
        dos_donts: t("Do's and don'ts"),
    }[type];
}

export function templateLabel(t: Translate, template: GuidelineTemplate) {
    return {
        logo: {
            title: t('Logo'),
            description: t('Logo versions, bilingual lockups and misuse'),
        },
        colour: {
            title: t('Colour'),
            description: t('Brand colours with their codes'),
        },
        type: {
            title: t('Typography'),
            description: t('Ethiopic and Latin typefaces and sizes'),
        },
        voice: {
            title: t('Voice'),
            description: t('How the brand speaks, with examples'),
        },
        blank: {
            title: t('Blank page'),
            description: t('Start from nothing'),
        },
    }[template];
}

export const emptyText = (): Bilingual => ({ en: '', am: '' });

function newId(): string {
    return Math.random().toString(36).slice(2, 14);
}

export function newBlock(type: GuidelineBlockType): GuidelineBlock {
    const id = newId();

    switch (type) {
        case 'heading':
        case 'text':
            return { id, type, text: emptyText() };
        case 'colours':
            return {
                id,
                type,
                swatches: [
                    { name: emptyText(), hex: '#01803B', note: emptyText() },
                ],
            };
        case 'type_pairing':
            return {
                id,
                type,
                latin: {
                    family: 'Instrument Sans',
                    assetId: null,
                    weight: 600,
                    sample: 'The quick brown fox jumps over the lazy dog',
                },
                ethiopic: {
                    family: 'Noto Sans Ethiopic',
                    assetId: null,
                    weight: 500,
                    sample: 'ሀ ለ ሐ መ ሠ ረ ሰ ሸ ቀ በ ተ ቸ ነ ኘ አ ከ ወ ዘ የ ደ ጀ ገ ጠ ጨ ፈ ፐ',
                },
                scale: [
                    {
                        label: { en: 'Headline', am: '' },
                        latin: 40,
                        ethiopic: 36,
                    },
                    { label: { en: 'Body', am: '' }, latin: 16, ethiopic: 16 },
                ],
                note: emptyText(),
            };
        case 'logos':
            return {
                id,
                type,
                items: [
                    {
                        assetId: null,
                        caption: emptyText(),
                        background: 'light',
                    },
                ],
            };
        case 'dos_donts':
            return {
                id,
                type,
                items: [
                    { kind: 'do', assetId: null, caption: emptyText() },
                    { kind: 'dont', assetId: null, caption: emptyText() },
                ],
            };
    }
}

export function isBlank(text: Bilingual): boolean {
    return text.en.trim() === '' && text.am.trim() === '';
}

/**
 * The text to show for one language, falling back to the other language
 * when it has not been written yet.
 */
export function pick(
    text: Bilingual,
    language: Exclude<ReadingLanguage, 'both'>,
): string {
    const other = language === 'en' ? 'am' : 'en';

    return text[language].trim() !== '' ? text[language] : text[other];
}

export function pageTitle(title: Bilingual, t: Translate): string {
    return title.en || title.am || t('Untitled page');
}

/* Colour codes ---------------------------------------------------------- */

export function hexToRgb(hex: string): [number, number, number] {
    const value = parseInt(hex.replace('#', ''), 16);

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

/**
 * A rough CMYK for briefing printers. Real print values come from the
 * brand's own swatch book, so the page says it is approximate.
 */
export function hexToCmyk(hex: string): [number, number, number, number] {
    const [r, g, b] = hexToRgb(hex).map((channel) => channel / 255);
    const k = 1 - Math.max(r, g, b);

    if (k >= 1) {
        return [0, 0, 0, 100];
    }

    return [
        Math.round(((1 - r - k) / (1 - k)) * 100),
        Math.round(((1 - g - k) / (1 - k)) * 100),
        Math.round(((1 - b - k) / (1 - k)) * 100),
        Math.round(k * 100),
    ];
}

function luminance(hex: string): number {
    const [r, g, b] = hexToRgb(hex).map((channel) => {
        const value = channel / 255;

        return value <= 0.03928
            ? value / 12.92
            : Math.pow((value + 0.055) / 1.055, 2.4);
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastRatio(a: string, b: string): number {
    const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (light + 0.05) / (dark + 0.05);
}

/**
 * White or black text, whichever reads better on this colour, and whether
 * it passes WCAG AA for body text.
 */
export function textOn(hex: string): {
    colour: '#FFFFFF' | '#000000';
    ratio: number;
    passes: boolean;
} {
    const onWhite = contrastRatio(hex, '#FFFFFF');
    const onBlack = contrastRatio(hex, '#000000');
    const colour = onWhite >= onBlack ? '#FFFFFF' : '#000000';
    const ratio = Math.max(onWhite, onBlack);

    return { colour, ratio, passes: ratio >= 4.5 };
}

/* Typefaces ------------------------------------------------------------- */

const BUNDLED: Record<string, string> = {
    'instrument sans': 'var(--font-sans)',
    'noto sans ethiopic': 'var(--font-ethiopic)',
};

function fontFaceName(assetId: string): string {
    return `guideline-font-${assetId}`;
}

/**
 * The CSS font stack for a typeface on a page: the brand's own font file
 * when one is chosen from the library, then the named family if this device
 * has it, then the app's own font for the script.
 */
export function fontStack(
    font: FontSpec,
    script: 'latin' | 'ethiopic',
    assets: Record<string, GuidelineAsset>,
): string {
    const fallback =
        script === 'ethiopic' ? 'var(--font-ethiopic)' : 'var(--font-sans)';
    const stack: string[] = [];

    if (font.assetId && assets[font.assetId]?.fontUrl) {
        stack.push(`"${fontFaceName(font.assetId)}"`);
    }

    const family = font.family.replace(/["'\\;{}<>]/g, '').trim();

    if (family !== '') {
        stack.push(BUNDLED[family.toLowerCase()] ?? `"${family}"`);
    }

    stack.push(fallback);

    return stack.join(', ');
}

/**
 * Loads the brand's own font files used on this page, and only those.
 */
export function useGuidelineFonts(
    blocks: GuidelineBlock[],
    assets: Record<string, GuidelineAsset>,
): void {
    const urls = blocks
        .flatMap((block) =>
            block.type === 'type_pairing'
                ? [block.latin.assetId, block.ethiopic.assetId]
                : [],
        )
        .filter((id): id is string => id !== null && !!assets[id]?.fontUrl)
        .map((id) => [id, assets[id].fontUrl as string] as const);

    const key = JSON.stringify(urls);

    useEffect(() => {
        if (typeof document === 'undefined' || !('fonts' in document)) {
            return;
        }

        for (const [id, url] of JSON.parse(key) as [string, string][]) {
            const name = fontFaceName(id);
            const loaded = [...document.fonts].some(
                (face) => face.family === name || face.family === `"${name}"`,
            );

            if (!loaded) {
                const face = new FontFace(name, `url("${url}")`, {
                    display: 'swap',
                });
                document.fonts.add(face);
                face.load().catch(() => document.fonts.delete(face));
            }
        }
    }, [key]);
}
