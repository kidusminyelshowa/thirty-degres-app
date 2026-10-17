import { useEffect } from 'react';
import type { Translate } from '@/lib/i18n';
import type {
    ContentLanguage,
    FontFaceSpec,
    GuidelineAsset,
    GuidelineBlock,
    GuidelineBlockType,
    GuidelineTemplate,
    LocalText,
    Script,
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
            description: t('Logo versions, lockups and misuse'),
        },
        colour: {
            title: t('Colour'),
            description: t('Brand colours with their codes'),
        },
        type: {
            title: t('Typography'),
            description: t('Typefaces for each script, with sizes'),
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

export const emptyText = (): LocalText => ({});

function newId(): string {
    return Math.random().toString(36).slice(2, 14);
}

export const scripts: Script[] = ['ethiopic', 'latin', 'arabic'];

export function scriptLabel(t: Translate, script: Script): string {
    return {
        ethiopic: t('Ethiopic (Ge’ez)'),
        latin: t('Latin'),
        arabic: t('Arabic'),
    }[script];
}

/** The specimen letters shown large for each script. */
export const scriptSpecimen: Record<Script, string> = {
    ethiopic: 'ሀለሐ',
    latin: 'Aa',
    arabic: 'أبج',
};

const defaultFaces: Record<Script, FontFaceSpec> = {
    ethiopic: {
        script: 'ethiopic',
        family: 'Noto Sans Ethiopic',
        assetId: null,
        weight: 500,
        sample: 'ሀ ለ ሐ መ ሠ ረ ሰ ሸ ቀ በ ተ ቸ ነ ኘ አ ከ ወ ዘ የ ደ ጀ ገ ጠ ጨ ፈ ፐ',
    },
    latin: {
        script: 'latin',
        family: 'Instrument Sans',
        assetId: null,
        weight: 600,
        sample: 'The quick brown fox jumps over the lazy dog',
    },
    arabic: {
        script: 'arabic',
        family: '',
        assetId: null,
        weight: 500,
        sample: 'ا ب ت ث ج ح خ د ذ ر ز س ش ص ض ط ظ ع غ ف ق ك ل م ن ه و ي',
    },
};

export function defaultFace(script: Script): FontFaceSpec {
    return { ...defaultFaces[script] };
}

/**
 * The scripts a brand's languages are written in, in the brand's order.
 */
export function scriptsOf(languages: ContentLanguage[]): Script[] {
    return [...new Set(languages.map((language) => language.script))];
}

export function newBlock(
    type: GuidelineBlockType,
    languages: ContentLanguage[],
): GuidelineBlock {
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
        case 'type_pairing': {
            const used = scriptsOf(languages);

            return {
                id,
                type,
                faces: used.map(defaultFace),
                scale: [
                    {
                        label: { en: 'Headline' },
                        sizes: Object.fromEntries(used.map((s) => [s, 36])),
                    },
                    {
                        label: { en: 'Body' },
                        sizes: Object.fromEntries(used.map((s) => [s, 16])),
                    },
                ],
                note: emptyText(),
            };
        }
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

export function written(text: LocalText, code: string): string {
    return (text[code] ?? '').trim();
}

export function isBlank(text: LocalText): boolean {
    return Object.values(text).every((value) => (value ?? '').trim() === '');
}

/**
 * The language to show a text in: the one asked for if it has been
 * written, otherwise the brand's first language that has, otherwise any.
 */
export function pick(
    text: LocalText,
    code: string,
    languages: ContentLanguage[],
): { code: string; value: string } | null {
    const order = [
        code,
        ...languages.map((language) => language.code),
        ...Object.keys(text),
    ];

    for (const candidate of order) {
        const value = written(text, candidate);

        if (value !== '') {
            return { code: candidate, value };
        }
    }

    return null;
}

export function pageTitle(
    title: LocalText,
    t: Translate,
    languages: ContentLanguage[] = [],
): string {
    return pick(title, 'en', languages)?.value ?? t('Untitled page');
}

/**
 * Right-to-left languages, for the dir attribute.
 */
export function directionOf(code: string, languages: ContentLanguage[]) {
    return (
        languages.find((language) => language.code === code)?.dir ??
        (code === 'ar' ? 'rtl' : 'ltr')
    );
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

const FALLBACK: Record<Script, string> = {
    ethiopic: 'var(--font-ethiopic)',
    latin: 'var(--font-sans)',
    // No Arabic font is bundled; every phone and computer has one.
    arabic: 'system-ui, sans-serif',
};

function fontFaceName(assetId: string): string {
    return `guideline-font-${assetId}`;
}

/**
 * The CSS font stack for a typeface on a page: the brand's own font file
 * when one is chosen from the library, then the named family if this device
 * has it, then a font for the script.
 */
export function fontStack(
    face: FontFaceSpec,
    assets: Record<string, GuidelineAsset>,
): string {
    const stack: string[] = [];

    if (face.assetId && assets[face.assetId]?.fontUrl) {
        stack.push(`"${fontFaceName(face.assetId)}"`);
    }

    const family = face.family.replace(/["'\\;{}<>]/g, '').trim();

    if (family !== '') {
        stack.push(BUNDLED[family.toLowerCase()] ?? `"${family}"`);
    }

    stack.push(FALLBACK[face.script]);

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
                ? block.faces.map((face) => face.assetId)
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
