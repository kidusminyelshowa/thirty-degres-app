import { Check, Copy, ImageOff, X } from 'lucide-react';
import { useState } from 'react';
import type { CSSProperties, ElementType } from 'react';
import {
    fontStack,
    hexToCmyk,
    hexToRgb,
    isBlank,
    pick,
    textOn,
} from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    Bilingual,
    ColoursBlock,
    DosDontsBlock,
    GuidelineAsset,
    GuidelineBlock,
    LogosBlock,
    ReadingLanguage,
    TypePairingBlock,
} from '@/types';

type Assets = Record<string, GuidelineAsset>;

/**
 * Text written in both languages, shown as the reader chose. Amharic is
 * marked lang="am" so it gets the Ethiopic font and line height.
 */
export function Bi({
    text,
    language,
    as: Tag = 'p',
    className,
    amClassName,
}: {
    text: Bilingual;
    language: ReadingLanguage;
    as?: ElementType;
    className?: string;
    amClassName?: string;
}) {
    if (isBlank(text)) {
        return null;
    }

    if (language !== 'both') {
        const value = pick(text, language);
        const isAm = value === text.am && text.am.trim() !== '';

        return (
            <Tag
                lang={isAm ? 'am' : 'en'}
                className={cn(
                    'whitespace-pre-line',
                    className,
                    isAm && amClassName,
                )}
            >
                {value}
            </Tag>
        );
    }

    return (
        <>
            {text.en.trim() !== '' && (
                <Tag lang="en" className={cn('whitespace-pre-line', className)}>
                    {text.en}
                </Tag>
            )}
            {text.am.trim() !== '' && (
                <Tag
                    lang="am"
                    className={cn(
                        'whitespace-pre-line',
                        className,
                        amClassName,
                    )}
                >
                    {text.am}
                </Tag>
            )}
        </>
    );
}

export function BlockView({
    block,
    language,
    assets,
}: {
    block: GuidelineBlock;
    language: ReadingLanguage;
    assets: Assets;
}) {
    switch (block.type) {
        case 'heading':
            return (
                <div className="flex flex-col gap-1 pt-4">
                    <Bi
                        text={block.text}
                        language={language}
                        as="h2"
                        className="text-2xl font-semibold tracking-tight"
                        amClassName="font-medium tracking-normal"
                    />
                </div>
            );
        case 'text':
            return (
                <div className="flex max-w-prose flex-col gap-3 text-base text-foreground/90">
                    <Bi text={block.text} language={language} />
                </div>
            );
        case 'colours':
            return <ColoursView block={block} language={language} />;
        case 'type_pairing':
            return (
                <TypePairingView
                    block={block}
                    language={language}
                    assets={assets}
                />
            );
        case 'logos':
            return (
                <LogosView block={block} language={language} assets={assets} />
            );
        case 'dos_donts':
            return (
                <DosDontsView
                    block={block}
                    language={language}
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

function LogosView({
    block,
    language,
    assets,
}: {
    block: LogosBlock;
    language: ReadingLanguage;
    assets: Assets;
}) {
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
                            alt={pick(item.caption, 'en')}
                            className={cn(
                                'h-full w-full',
                                item.background === 'dark'
                                    ? 'text-white/70'
                                    : 'text-neutral-500',
                            )}
                        />
                    </div>
                    <figcaption className="flex flex-col gap-0.5 text-sm text-muted-foreground">
                        <Bi text={item.caption} language={language} as="span" />
                    </figcaption>
                </figure>
            ))}
        </div>
    );
}

function ColoursView({
    block,
    language,
}: {
    block: ColoursBlock;
    language: ReadingLanguage;
}) {
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
                                <Bi
                                    text={swatch.name}
                                    language={language}
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
                                <Bi
                                    text={swatch.note}
                                    language={language}
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
    language,
    assets,
}: {
    block: TypePairingBlock;
    language: ReadingLanguage;
    assets: Assets;
}) {
    const { t } = useTranslation();
    const latin: CSSProperties = {
        fontFamily: fontStack(block.latin, 'latin', assets),
        fontWeight: block.latin.weight,
    };
    const ethiopic: CSSProperties = {
        fontFamily: fontStack(block.ethiopic, 'ethiopic', assets),
        fontWeight: block.ethiopic.weight,
        lineHeight: 1.8,
    };

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 md:grid-cols-2">
                {(
                    [
                        ['ethiopic', t('Ethiopic'), block.ethiopic, ethiopic],
                        ['latin', t('Latin'), block.latin, latin],
                    ] as const
                ).map(([script, label, font, style]) => (
                    <div
                        key={script}
                        className="flex flex-col gap-3 rounded-xl border p-4"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <span className="text-muted-foreground">
                                {label}
                            </span>
                            <span className="font-medium">
                                {font.family ||
                                    (font.assetId
                                        ? assets[font.assetId]?.name
                                        : '')}{' '}
                                · {font.weight}
                            </span>
                        </div>
                        <p
                            className="text-5xl"
                            style={style}
                            lang={script === 'ethiopic' ? 'am' : 'en'}
                        >
                            {script === 'ethiopic' ? 'ሀለሐ' : 'Aa'}
                        </p>
                        {font.sample !== '' && (
                            <p
                                className="text-lg break-words"
                                style={style}
                                lang={script === 'ethiopic' ? 'am' : 'en'}
                            >
                                {font.sample}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            {block.scale.length > 0 && (
                <div className="flex flex-col divide-y rounded-xl border">
                    {block.scale.map((row, index) => (
                        <div
                            key={index}
                            className="grid gap-2 p-4 md:grid-cols-[10rem_1fr_1fr] md:items-baseline"
                        >
                            <div className="flex flex-col text-sm text-muted-foreground">
                                <Bi
                                    text={row.label}
                                    language={language}
                                    as="span"
                                />
                                <span className="text-xs">
                                    {row.ethiopic}px / {row.latin}px
                                </span>
                            </div>
                            <p
                                lang="am"
                                className="min-w-0 truncate"
                                style={{ ...ethiopic, fontSize: row.ethiopic }}
                            >
                                {firstWords(block.ethiopic.sample) || 'ሀለሐመ'}
                            </p>
                            <p
                                lang="en"
                                className="min-w-0 truncate"
                                style={{ ...latin, fontSize: row.latin }}
                            >
                                {firstWords(block.latin.sample) || 'Aa Bb Cc'}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex max-w-prose flex-col gap-2 text-sm text-muted-foreground">
                <Bi text={block.note} language={language} />
            </div>
        </div>
    );
}

function firstWords(sample: string): string {
    return sample.split(/\s+/).slice(0, 4).join(' ');
}

function DosDontsView({
    block,
    language,
    assets,
}: {
    block: DosDontsBlock;
    language: ReadingLanguage;
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
                                    alt={pick(item.caption, 'en')}
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
                            <Bi text={item.caption} language={language} />
                        </figcaption>
                    </figure>
                );
            })}
        </div>
    );
}
