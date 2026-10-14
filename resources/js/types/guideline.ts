import type { AssetKind, AssetStatus } from './asset';

/**
 * Guideline page blocks, as stored by App\Guidelines\GuidelineBlocks. Keep
 * the two in step.
 */
export type Bilingual = { en: string; am: string };

export type FontSpec = {
    family: string;
    assetId: string | null;
    weight: number;
    sample: string;
};

export type HeadingBlock = { id: string; type: 'heading'; text: Bilingual };

export type TextBlock = { id: string; type: 'text'; text: Bilingual };

export type Swatch = { name: Bilingual; hex: string; note: Bilingual };

export type ColoursBlock = { id: string; type: 'colours'; swatches: Swatch[] };

export type TypeScaleRow = {
    label: Bilingual;
    latin: number;
    ethiopic: number;
};

export type TypePairingBlock = {
    id: string;
    type: 'type_pairing';
    latin: FontSpec;
    ethiopic: FontSpec;
    scale: TypeScaleRow[];
    note: Bilingual;
};

export type LogoItem = {
    assetId: string | null;
    caption: Bilingual;
    background: 'light' | 'dark';
};

export type LogosBlock = { id: string; type: 'logos'; items: LogoItem[] };

export type DoDontItem = {
    kind: 'do' | 'dont';
    assetId: string | null;
    caption: Bilingual;
};

export type DosDontsBlock = {
    id: string;
    type: 'dos_donts';
    items: DoDontItem[];
};

export type GuidelineBlock =
    | HeadingBlock
    | TextBlock
    | ColoursBlock
    | TypePairingBlock
    | LogosBlock
    | DosDontsBlock;

export type GuidelineBlockType = GuidelineBlock['type'];

export type GuidelineTemplate = 'blank' | 'logo' | 'colour' | 'type' | 'voice';

/** A library file a page points at. */
export type GuidelineAsset = {
    id: string;
    name: string;
    kind: AssetKind;
    status: AssetStatus;
    extension: string;
    previewUrl: string | null;
    fontUrl: string | null;
};

export type GuidelinePageSummary = {
    id: string;
    title: Bilingual;
    published: boolean;
    hasChanges: boolean;
};

/** Which language a reader shows. */
export type ReadingLanguage = 'both' | 'en' | 'am';
