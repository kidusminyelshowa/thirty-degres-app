import { Check, Copy, Download, ImageOff, X } from 'lucide-react';
import { createContext, useContext, useState } from 'react';
import type { ElementType, ReactNode } from 'react';
import {
    directionOf,
    fontStack,
    hexToCmyk,
    hexToRgb,
    isBlank,
    pick,
    scriptLabel,
    scriptSpecimen,
    textOn,
    written,
} from '@/lib/guidelines';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    ColoursBlock,
    ContentLanguage,
    DosDontsBlock,
    GuidelineAsset,
    GuidelineBlock,
    LocalText,
    LogosBlock,
    ReadingLanguage,
    TypePairingBlock,
} from '@/types';

type Assets = Record<string, GuidelineAsset>;

type Reading = { language: ReadingLanguage; languages: ContentLanguage[] };

const ReadingContext = createContext<Reading>({
    language: 'all',
    languages: [],
});

/**
 * Which language a page is read in, and the brand's languages in order.
 */
export function ReadingProvider({
    language,
    languages,
    children,
}: Reading & { children: ReactNode }) {
    return (
        <ReadingContext.Provider value={{ language, languages }}>
            {children}
        </ReadingContext.Provider>
    );
}

/**
 * Text written in several languages, shown as the reader chose: one
 * language (falling back to the brand's first language that has it), or
 * every language in the brand's order. Each is marked with its lang and
 * dir, so Ge'ez gets the Ethiopic font and Arabic runs right to left.
 */
export function Local({
    text,
    as: Tag = 'p',
    className,
    ethiopicClassName,
}: {
    text: LocalText;
    as?: ElementType;
    className?: string;
    ethiopicClassName?: string;
}) {
    const { language, languages } = useContext(ReadingContext);

    if (isBlank(text)) {
        return null;
    }

    const entries: { code: string; value: string }[] =
        language === 'all'
            ? [
                  ...languages.map((l) => l.code),
                  ...Object.keys(text).filter(
                      (code) => !languages.some((l) => l.code === code),
                  ),
              ]
                  .map((code) => ({ code, value: written(text, code) }))
                  .filter((entry) => entry.value !== '')
            : [pick(text, language, languages)].filter(
                  (entry): entry is { code: string; value: string } =>
                      entry !== null,
              );

    return (
        <>
            {entries.map(({ code, value }) => (
                <Tag
                    key={code}
                    lang={code}
                    dir={directionOf(code, languages)}
                    className={cn(
                        'whitespace-pre-line',
                        className,
                        scriptOf(code, languages) === 'ethiopic' &&
                            ethiopicClassName,
                    )}
                >
                    {value}
                </Tag>
            ))}
        </>
    );
}

/**
 * The brand's languages, in its order.
 */
export function useBrandLanguages(): ContentLanguage[] {
    return useContext(ReadingContext).languages;
}

function scriptOf(code: string, languages: ContentLanguage[]) {
    return languages.find((l) => l.code === code)?.script;
}

export function BlockView({
    block,
    assets,
}: {
    block: GuidelineBlock;
    assets: Assets;
}) {
    switch (block.type) {
        case 'heading':
            return (
                <div className="flex flex-col gap-1 pt-4">
                    <Local
                        text={block.text}

                        as="h2"
                        className="text-2xl font-semibold tracking-tight"
                        ethiopicClassName="font-medium tracking-normal"
                    />
                </div>
            );
        case 'text':
            return (
                <div className="flex max-w-prose flex-col gap-3 text-base text-foreground/90">
                    <Local text={block.text} />
                </div>
            );
        case 'colours':
            return <ColoursView block={block} />;
        case 'type_pairing':
            return (
                <TypePairingView
                    block={block}

                    assets={assets}
                />
            );
        case 'logos':
            return <LogosView block={block} assets={assets} />;
        case 'dos_donts':
            return (
                <DosDontsView
                    block={block}

                    assets={assets}
                />
            );
    }
}

function AssetImage({
    asset,
    className,
    alt,
}: {
    asset: GuidelineAsset | undefined;
    className?: string;
    alt: string;
}) {
    const { t } = useTranslation();

    if (!asset?.previewUrl) {
        return (
            <div
                className={cn(
                    'flex flex-col items-center justify-center gap-2 text-xs text-muted-foreground',
                    className,
                )}
            >
                <ImageOff className="size-6" aria-hidden />
                <span>{asset ? asset.name : t('No picture yet')}</span>
            </div>
        );
    }

    return (
        <img
            src={asset.previewUrl}
            alt={alt}
            loading="lazy"
            decoding="async"
            className={cn('object-contain', className)}
        />
    );
}

/**
 * The original file, through the library's download route, so people use
 * the real font or logo instead of a lookalike.
 */
function DownloadLink({ asset }: { asset: GuidelineAsset | undefined }) {
    const { t } = useTranslation();

    if (!asset?.downloadUrl) {
        return null;
    }

    return (
        <Button variant="outline" size="sm" asChild className="shrink-0">
            <a
                href={asset.downloadUrl}
                download
                aria-label={t('Download :name', { name: asset.name })}
            >
                <Download aria-hidden />
                {t('Download')}
            </a>
        </Button>
    );
}

function LogosView({ block, assets }: { block: LogosBlock; assets: Assets }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {block.items.map((item, index) => (
                <figure key={index} className="flex flex-col gap-2">
                    <div
                        className={cn(
                            'flex aspect-[3/2] items-center justify-center rounded-xl border p-6',
                            item.background === 'dark'
                                ? 'border-transparent bg-ink'
                                : 'bg-white',
                        )}
                    >
                        <AssetImage
                            asset={
                                item.assetId ? assets[item.assetId] : undefined
                            }
                            alt={written(item.caption, 'en')}
                            className={cn(
                                'h-full w-full',
                                item.background === 'dark'
                                    ? 'text-white/70'
                                    : 'text-neutral-500',
                            )}
                        />
                    </div>
                    <figcaption className="flex items-start justify-between gap-2 text-sm text-muted-foreground">
                        <Local text={item.caption} as="span" />
                        <DownloadLink
                            asset={
                                item.assetId ? assets[item.assetId] : undefined
                            }
                        />
                    </figcaption>
                </figure>
            ))}
        </div>
    );
}

function ColoursView({ block }: { block: ColoursBlock }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                {block.swatches.map((swatch, index) => {
                    const text = textOn(swatch.hex);
                    const [r, g, b] = hexToRgb(swatch.hex);
                    const [c, m, y, k] = hexToCmyk(swatch.hex);

                    return (
                        <div
                            key={index}
                            className="flex flex-col overflow-hidden rounded-xl border"
                        >
                            <div
                                className="flex aspect-[4/3] items-end p-3 text-sm font-medium"
                                style={{
                                    backgroundColor: swatch.hex,
                                    color: text.colour,
                                }}
                            >
                                <span title={t('Text contrast')}>
                                    Aa ሀ {text.ratio.toFixed(1)}:1
                                    {text.passes ? '' : ' !'}
                                </span>
                            </div>
                            <div className="flex flex-col gap-1 p-3 text-sm">
                                <Local
                                    text={swatch.name}

                                    className="font-medium"
                                />
                                <CopyValue value={swatch.hex} />
                                <span className="text-xs text-muted-foreground">
                                    RGB {r} {g} {b}
                                </span>
                                <span
                                    className="text-xs text-muted-foreground"
                                    title={t(
                                        'Approximate. Confirm print colours with your printer.',
                                    )}
                                >
                                    CMYK ≈ {c} {m} {y} {k}
                                </span>
                                <Local
                                    text={swatch.note}

                                    className="pt-1 text-xs text-muted-foreground"
                                />
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function CopyValue({ value }: { value: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    return (
        <button
            type="button"
            onClick={() => {
                void navigator.clipboard?.writeText(value).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                });
            }}
            className="flex w-fit items-center gap-1.5 font-mono text-xs hover:text-primary"
            aria-label={t('Copy :value', { value })}
        >
            {value}
            {copied ? (
                <Check className="size-3" aria-hidden />
            ) : (
                <Copy className="size-3" aria-hidden />
            )}
        </button>
    );
}

function TypePairingView({
    block,
    assets,
}: {
    block: TypePairingBlock;
    assets: Assets;
}) {
    const { t } = useTranslation();
    const style = (face: TypePairingBlock['faces'][number]) => ({
        fontFamily: fontStack(face, assets),
        fontWeight: face.weight,
        lineHeight: face.script === 'latin' ? undefined : 1.8,
    });
    const langFor = (script: string) =>
        ({ ethiopic: 'am', latin: 'en', arabic: 'ar' })[script] ?? 'en';

    return (
        <div className="flex flex-col gap-4">
            <div
                className={cn(
                    'grid gap-4',
                    block.faces.length > 1 && 'md:grid-cols-2',
                    block.faces.length > 2 && 'xl:grid-cols-3',
                )}
            >
                {block.faces.map((face, index) => (
                    <div
                        key={index}
                        className="flex flex-col gap-3 rounded-xl border p-4"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                {scriptLabel(t, face.script)}
                            </span>
                            <span className="font-medium">
                                {face.family ||
                                    (face.assetId
                                        ? assets[face.assetId]?.name
                                        : '')}{' '}
                                · {face.weight}
                            </span>
                        </div>
                        <p
                            className="text-5xl"
                            style={style(face)}
                            lang={langFor(face.script)}
                            dir={face.script === 'arabic' ? 'rtl' : 'ltr'}
                        >
                            {scriptSpecimen[face.script]}
                        </p>
                        {face.sample !== '' && (
                            <p
                                className="text-lg break-words"
                                style={style(face)}
                                lang={langFor(face.script)}
                                dir={face.script === 'arabic' ? 'rtl' : 'ltr'}
                            >
                                {face.sample}
                            </p>
                        )}
                        {face.assetId && assets[face.assetId] && (
                            <div className="mt-auto flex flex-wrap items-center justify-between gap-2 border-t pt-3 text-sm text-muted-foreground">
                                <span>
                                    {t('Install this font before you design.')}
                                </span>
                                <DownloadLink asset={assets[face.assetId]} />
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {block.scale.length > 0 && (
                <div className="flex flex-col divide-y rounded-xl border">
                    {block.scale.map((row, index) => (
                        <div
                            key={index}
                            className="grid gap-2 p-4 md:grid-cols-[10rem_1fr] md:items-baseline"
                        >
                            <div className="flex flex-col text-sm text-muted-foreground">
                                <Local text={row.label} as="span" />
                                <span className="text-xs">
                                    {block.faces
                                        .map((face) =>
                                            row.sizes[face.script]
                                                ? `${row.sizes[face.script]}px`
                                                : null,
                                        )
                                        .filter(Boolean)
                                        .join(' / ')}
                                </span>
                            </div>
                            <div className="flex min-w-0 flex-col gap-2">
                                {block.faces.map(
                                    (face, faceIndex) =>
                                        row.sizes[face.script] && (
                                            <p
                                                key={faceIndex}
                                                lang={langFor(face.script)}
                                                dir={
                                                    face.script === 'arabic'
                                                        ? 'rtl'
                                                        : 'ltr'
                                                }
                                                className="min-w-0 truncate"
                                                style={{
                                                    ...style(face),
                                                    fontSize:
                                                        row.sizes[face.script],
                                                }}
                                            >
                                                {firstWords(face.sample) ||
                                                    scriptSpecimen[face.script]}
                                            </p>
                                        ),
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex max-w-prose flex-col gap-2 text-sm text-muted-foreground">
                <Local text={block.note} />
            </div>
        </div>
    );
}

function firstWords(sample: string): string {
    return sample.split(/\s+/).slice(0, 4).join(' ');
}

function DosDontsView({
    block,
    assets,
}: {
    block: DosDontsBlock;
    assets: Assets;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {block.items.map((item, index) => {
                const isDo = item.kind === 'do';

                return (
                    <figure
                        key={index}
                        className={cn(
                            'flex flex-col overflow-hidden rounded-xl border-2',
                            isDo
                                ? 'border-brand-green/60 dark:border-lime/60'
                                : 'border-destructive/60',
                        )}
                    >
                        {item.assetId && (
                            <div className="flex aspect-[3/2] items-center justify-center bg-white p-4">
                                <AssetImage
                                    asset={assets[item.assetId]}
                                    alt={written(item.caption, 'en')}
                                    className="h-full w-full text-neutral-500"
                                />
                            </div>
                        )}
                        <figcaption className="flex flex-col gap-1 p-3 text-sm">
                            <span
                                className={cn(
                                    'flex items-center gap-1 font-medium',
                                    isDo
                                        ? 'text-brand-green dark:text-lime'
                                        : 'text-destructive-foreground',
                                )}
                            >
                                {isDo ? (
                                    <Check className="size-4" aria-hidden />
                                ) : (
                                    <X className="size-4" aria-hidden />
                                )}
                                {isDo ? t('Do') : t("Don't")}
                            </span>
                            <Local text={item.caption} />
                        </figcaption>
                    </figure>
                );
            })}
        </div>
    );
}
