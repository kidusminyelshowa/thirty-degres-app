import { ImageOff, Plus, Search, Trash2, Type } from 'lucide-react';
import { useId, useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { statusLabel } from '@/lib/assets';
import { emptyText, textOn } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type {
    Bilingual,
    FontSpec,
    GuidelineAsset,
    GuidelineBlock,
} from '@/types';

type Library = GuidelineAsset[];

/**
 * The form for one block. Every change hands back the whole new block.
 */
export function BlockEditor({
    block,
    onChange,
    library,
}: {
    block: GuidelineBlock;
    onChange: (block: GuidelineBlock) => void;
    library: Library;
}) {
    const { t } = useTranslation();

    switch (block.type) {
        case 'heading':
            return (
                <BilingualField
                    label={t('Heading')}
                    value={block.text}
                    onChange={(text) => onChange({ ...block, text })}
                    maxLength={300}
                />
            );
        case 'text':
            return (
                <BilingualField
                    label={t('Text')}
                    value={block.text}
                    onChange={(text) => onChange({ ...block, text })}
                    multiline
                    maxLength={5000}
                />
            );
        case 'colours':
            return (
                <ListEditor
                    items={block.swatches}
                    onChange={(swatches) => onChange({ ...block, swatches })}
                    max={24}
                    addLabel={t('Add colour')}
                    create={() => ({
                        name: emptyText(),
                        hex: '#022018',
                        note: emptyText(),
                    })}
                    render={(swatch, update) => (
                        <div className="grid gap-3 md:grid-cols-[9rem_1fr]">
                            <HexField
                                value={swatch.hex}
                                onChange={(hex) => update({ ...swatch, hex })}
                            />
                            <div className="grid gap-3">
                                <BilingualField
                                    label={t('Colour name')}
                                    value={swatch.name}
                                    onChange={(name) =>
                                        update({ ...swatch, name })
                                    }
                                    maxLength={80}
                                />
                                <BilingualField
                                    label={t('When to use it')}
                                    value={swatch.note}
                                    onChange={(note) =>
                                        update({ ...swatch, note })
                                    }
                                    maxLength={300}
                                />
                            </div>
                        </div>
                    )}
                />
            );
        case 'type_pairing':
            return (
                <div className="grid gap-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <FontEditor
                            label={t('Ethiopic typeface')}
                            script="ethiopic"
                            value={block.ethiopic}
                            onChange={(ethiopic) =>
                                onChange({ ...block, ethiopic })
                            }
                            library={library}
                        />
                        <FontEditor
                            label={t('Latin typeface')}
                            script="latin"
                            value={block.latin}
                            onChange={(latin) => onChange({ ...block, latin })}
                            library={library}
                        />
                    </div>
                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            {t('Sizes')}
                        </span>
                        <ListEditor
                            items={block.scale}
                            onChange={(scale) => onChange({ ...block, scale })}
                            max={8}
                            addLabel={t('Add size')}
                            create={() => ({
                                label: emptyText(),
                                latin: 16,
                                ethiopic: 16,
                            })}
                            render={(row, update) => (
                                <div className="grid gap-3 md:grid-cols-[1fr_7rem_7rem]">
                                    <BilingualField
                                        label={t('Use')}
                                        value={row.label}
                                        onChange={(label) =>
                                            update({ ...row, label })
                                        }
                                        maxLength={80}
                                    />
                                    <NumberField
                                        label={t('Ethiopic px')}
                                        value={row.ethiopic}
                                        onChange={(ethiopic) =>
                                            update({ ...row, ethiopic })
                                        }
                                    />
                                    <NumberField
                                        label={t('Latin px')}
                                        value={row.latin}
                                        onChange={(latin) =>
                                            update({ ...row, latin })
                                        }
                                    />
                                </div>
                            )}
                        />
                    </div>
                    <BilingualField
                        label={t('Notes on using them together')}
                        value={block.note}
                        onChange={(note) => onChange({ ...block, note })}
                        multiline
                        maxLength={5000}
                    />
                </div>
            );
        case 'logos':
            return (
                <ListEditor
                    items={block.items}
                    onChange={(items) => onChange({ ...block, items })}
                    max={12}
                    addLabel={t('Add logo')}
                    create={() => ({
                        assetId: null,
                        caption: emptyText(),
                        background: 'light' as const,
                    })}
                    render={(item, update) => (
                        <div className="grid gap-3 md:grid-cols-[12rem_1fr]">
                            <AssetPicker
                                value={item.assetId}
                                onChange={(assetId) =>
                                    update({ ...item, assetId })
                                }
                                library={library}
                                background={item.background}
                            />
                            <div className="grid gap-3">
                                <BilingualField
                                    label={t('Caption')}
                                    value={item.caption}
                                    onChange={(caption) =>
                                        update({ ...item, caption })
                                    }
                                    maxLength={300}
                                />
                                <div className="grid gap-2">
                                    <Label>{t('Background')}</Label>
                                    <Select
                                        value={item.background}
                                        onValueChange={(background) =>
                                            update({
                                                ...item,
                                                background: background as
                                                    | 'light'
                                                    | 'dark',
                                            })
                                        }
                                    >
                                        <SelectTrigger className="w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">
                                                {t('Light')}
                                            </SelectItem>
                                            <SelectItem value="dark">
                                                {t('Dark')}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    )}
                />
            );
        case 'dos_donts':
            return (
                <ListEditor
                    items={block.items}
                    onChange={(items) => onChange({ ...block, items })}
                    max={16}
                    addLabel={t('Add example')}
                    create={() => ({
                        kind: 'do' as const,
                        assetId: null,
                        caption: emptyText(),
                    })}
                    render={(item, update) => (
                        <div className="grid gap-3 md:grid-cols-[12rem_1fr]">
                            <div className="grid content-start gap-3">
                                <Select
                                    value={item.kind}
                                    onValueChange={(kind) =>
                                        update({
                                            ...item,
                                            kind: kind as 'do' | 'dont',
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label={t('Do or don’t')}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="do">
                                            {t('Do')}
                                        </SelectItem>
                                        <SelectItem value="dont">
                                            {t("Don't")}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <AssetPicker
                                    value={item.assetId}
                                    onChange={(assetId) =>
                                        update({ ...item, assetId })
                                    }
                                    library={library}
                                    optional
                                />
                            </div>
                            <BilingualField
                                label={t('Example')}
                                value={item.caption}
                                onChange={(caption) =>
                                    update({ ...item, caption })
                                }
                                multiline
                                maxLength={300}
                            />
                        </div>
                    )}
                />
            );
    }
}

function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm',
                className,
            )}
            {...props}
        />
    );
}

/**
 * The same text in English and Amharic, side by side on wide screens.
 */
export function BilingualField({
    label,
    value,
    onChange,
    multiline = false,
    maxLength,
}: {
    label: string;
    value: Bilingual;
    onChange: (value: Bilingual) => void;
    multiline?: boolean;
    maxLength: number;
}) {
    const id = useId();
    const Field = multiline ? Textarea : Input;

    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">{label}</legend>
            <div className="grid gap-2 md:grid-cols-2">
                <div className="grid gap-1">
                    <Label
                        htmlFor={`${id}-am`}
                        className="text-xs text-muted-foreground"
                        lang="am"
                    >
                        አማርኛ
                    </Label>
                    <Field
                        id={`${id}-am`}
                        lang="am"
                        value={value.am}
                        maxLength={maxLength}
                        onChange={(event) =>
                            onChange({ ...value, am: event.target.value })
                        }
                    />
                </div>
                <div className="grid gap-1">
                    <Label
                        htmlFor={`${id}-en`}
                        className="text-xs text-muted-foreground"
                    >
                        English
                    </Label>
                    <Field
                        id={`${id}-en`}
                        lang="en"
                        value={value.en}
                        maxLength={maxLength}
                        onChange={(event) =>
                            onChange({ ...value, en: event.target.value })
                        }
                    />
                </div>
            </div>
        </fieldset>
    );
}

function HexField({
    value,
    onChange,
}: {
    value: string;
    onChange: (hex: string) => void;
}) {
    const { t } = useTranslation();
    const id = useId();
    const [draft, setDraft] = useState(value);
    const [shown, setShown] = useState(value);

    // Follow changes from outside, e.g. a colour above being removed.
    if (value !== shown) {
        setShown(value);
        setDraft(value);
    }

    const valid = /^#[0-9A-Fa-f]{6}$/.test(draft);

    return (
        <div className="grid content-start gap-2">
            <div
                className="flex aspect-square items-end rounded-lg border p-2 text-xs font-medium"
                style={{ backgroundColor: value, color: textOn(value).colour }}
            >
                {textOn(value).ratio.toFixed(1)}:1
            </div>
            <div className="flex gap-2">
                <input
                    type="color"
                    value={value}
                    onChange={(event) => {
                        const hex = event.target.value.toUpperCase();
                        setDraft(hex);
                        setShown(hex);
                        onChange(hex);
                    }}
                    aria-label={t('Pick a colour')}
                    className="h-9 w-10 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
                />
                <Input
                    id={id}
                    value={draft}
                    aria-label={t('Hex code')}
                    aria-invalid={!valid}
                    maxLength={7}
                    className="font-mono"
                    onChange={(event) => {
                        let hex = event.target.value.trim();

                        if (!hex.startsWith('#')) {
                            hex = `#${hex}`;
                        }

                        setDraft(hex);

                        if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
                            setShown(hex.toUpperCase());
                            onChange(hex.toUpperCase());
                        }
                    }}
                />
            </div>
        </div>
    );
}

function NumberField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: number;
    onChange: (value: number) => void;
}) {
    const id = useId();

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id} className="mt-7 text-xs text-muted-foreground">
                {label}
            </Label>
            <Input
                id={id}
                type="number"
                min={8}
                max={160}
                value={value}
                onChange={(event) => {
                    const next = Math.round(Number(event.target.value));

                    if (Number.isFinite(next)) {
                        onChange(Math.min(160, Math.max(8, next)));
                    }
                }}
            />
        </div>
    );
}

function FontEditor({
    label,
    script,
    value,
    onChange,
    library,
}: {
    label: string;
    script: 'latin' | 'ethiopic';
    value: FontSpec;
    onChange: (value: FontSpec) => void;
    library: Library;
}) {
    const { t } = useTranslation();
    const id = useId();
    const fonts = library.filter((asset) => asset.fontUrl !== null);
    const NONE = 'none';

    return (
        <fieldset className="grid content-start gap-3 rounded-lg border p-3">
            <legend className="px-1 text-sm font-medium">{label}</legend>
            <div className="grid gap-1">
                <Label htmlFor={`${id}-family`} className="text-xs">
                    {t('Typeface name')}
                </Label>
                <Input
                    id={`${id}-family`}
                    value={value.family}
                    maxLength={80}
                    onChange={(event) =>
                        onChange({ ...value, family: event.target.value })
                    }
                />
            </div>
            <div className="grid gap-1">
                <Label className="text-xs">
                    {t('Font file from the library')}
                </Label>
                <Select
                    value={value.assetId ?? NONE}
                    onValueChange={(assetId) =>
                        onChange({
                            ...value,
                            assetId: assetId === NONE ? null : assetId,
                        })
                    }
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>
                            {t('None: show the name only')}
                        </SelectItem>
                        {fonts.map((font) => (
                            <SelectItem key={font.id} value={font.id}>
                                {font.name} ({font.extension.toUpperCase()})
                                {font.status !== 'approved'
                                    ? ` · ${statusLabel(t, font.status)}`
                                    : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {fonts.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                        <Type className="mr-1 inline size-3" aria-hidden />
                        {t(
                            'Upload a WOFF2, WOFF, TTF or OTF file to this brand to show type in its own font.',
                        )}
                    </p>
                )}
            </div>
            <div className="grid gap-1">
                <Label className="text-xs">{t('Weight')}</Label>
                <Select
                    value={String(value.weight)}
                    onValueChange={(weight) =>
                        onChange({ ...value, weight: Number(weight) })
                    }
                >
                    <SelectTrigger className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[300, 400, 500, 600, 700, 800].map((weight) => (
                            <SelectItem key={weight} value={String(weight)}>
                                {weight}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid gap-1">
                <Label htmlFor={`${id}-sample`} className="text-xs">
                    {t('Sample text')}
                </Label>
                <Input
                    id={`${id}-sample`}
                    lang={script === 'ethiopic' ? 'am' : 'en'}
                    value={value.sample}
                    maxLength={300}
                    onChange={(event) =>
                        onChange({ ...value, sample: event.target.value })
                    }
                />
            </div>
        </fieldset>
    );
}

/**
 * Pick a file from the brand's library, by its preview.
 */
function AssetPicker({
    value,
    onChange,
    library,
    background = 'light',
    optional = false,
}: {
    value: string | null;
    onChange: (assetId: string | null) => void;
    library: Library;
    background?: 'light' | 'dark';
    optional?: boolean;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = value ? library.find((asset) => asset.id === value) : null;
    const images = library.filter(
        (asset) =>
            asset.previewUrl !== null &&
            asset.name.toLowerCase().includes(query.trim().toLowerCase()),
    );

    return (
        <div className="grid content-start gap-2">
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={cn(
                    'flex aspect-[3/2] items-center justify-center overflow-hidden rounded-lg border p-3 text-xs text-muted-foreground hover:border-ring',
                    background === 'dark'
                        ? 'bg-ink text-white/70'
                        : 'bg-white text-neutral-500',
                )}
                aria-label={
                    selected ? t('Change picture') : t('Choose a picture')
                }
            >
                {selected?.previewUrl ? (
                    <img
                        src={selected.previewUrl}
                        alt={selected.name}
                        loading="lazy"
                        className="h-full w-full object-contain"
                    />
                ) : (
                    <span className="flex flex-col items-center gap-1">
                        {value ? (
                            <>
                                <ImageOff className="size-5" aria-hidden />
                                {t('File not available')}
                            </>
                        ) : (
                            <>
                                <Plus className="size-5" aria-hidden />
                                {optional
                                    ? t('Add a picture (optional)')
                                    : t('Choose a picture')}
                            </>
                        )}
                    </span>
                )}
            </button>
            {selected && selected.status !== 'approved' && (
                <span className="text-xs text-muted-foreground">
                    {t(':status: approve it before publishing', {
                        status: statusLabel(t, selected.status),
                    })}
                </span>
            )}
            {value && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(null)}
                >
                    {t('Remove picture')}
                </Button>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {t('Choose from the library')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Files from this brand. Upload new ones from the brand’s library page.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="relative">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t('Search by name')}
                            aria-label={t('Search by name')}
                            className="pl-9"
                        />
                    </div>
                    {images.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            {t('No pictures in this brand’s library yet.')}
                        </p>
                    ) : (
                        <div className="grid max-h-[60vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                            {images.map((asset) => (
                                <button
                                    key={asset.id}
                                    type="button"
                                    onClick={() => {
                                        onChange(asset.id);
                                        setOpen(false);
                                    }}
                                    className={cn(
                                        'flex flex-col gap-1 rounded-lg border p-2 text-left text-xs hover:border-ring',
                                        asset.id === value &&
                                            'border-ring ring-2 ring-ring/40',
                                    )}
                                >
                                    <span className="flex aspect-[3/2] items-center justify-center overflow-hidden rounded bg-white">
                                        <img
                                            src={asset.previewUrl ?? ''}
                                            alt=""
                                            loading="lazy"
                                            className="h-full w-full object-contain"
                                        />
                                    </span>
                                    <span className="truncate font-medium">
                                        {asset.name}
                                    </span>
                                    {asset.status !== 'approved' && (
                                        <span className="text-muted-foreground">
                                            {statusLabel(t, asset.status)}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

/**
 * A list of repeated rows (colours, logos, sizes) with add, move and remove.
 */
function ListEditor<T>({
    items,
    onChange,
    render,
    create,
    max,
    addLabel,
}: {
    items: T[];
    onChange: (items: T[]) => void;
    render: (item: T, update: (item: T) => void) => ReactNode;
    create: () => T;
    max: number;
    addLabel: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-3">
            {items.map((item, index) => (
                <div
                    key={index}
                    className="relative grid gap-2 rounded-lg border border-dashed p-3 pr-12"
                >
                    {render(item, (next) =>
                        onChange(
                            items.map((old, i) => (i === index ? next : old)),
                        ),
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute top-2 right-2"
                        onClick={() =>
                            onChange(items.filter((_, i) => i !== index))
                        }
                        aria-label={t('Remove')}
                    >
                        <Trash2 />
                    </Button>
                </div>
            ))}
            {items.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="w-fit"
                    onClick={() => onChange([...items, create()])}
                >
                    <Plus />
                    {addLabel}
                </Button>
            )}
        </div>
    );
}
