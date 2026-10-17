import type { AssetKind, AssetStatus } from './asset';

/*
 * Guideline page blocks, as stored by App\Guidelines\GuidelineBlocks. Keep
 * the two in step.
 */

/** Text in each language, by language code. Missing means not written. */
export type LocalText = Partial<Record<string, string>>;

export type Script = 'ethiopic' | 'latin' | 'arabic';

export type ContentLanguage = {
    code: string;
    name: string;
    native: string;
    script: Script;
    dir: 'ltr' | 'rtl';
};

export type FontFaceSpec = {
    script: Script;
    family: string;
    assetId: string | null;
    weight: number;
    sample: string;
};

export type HeadingBlock = { id: string; type: 'heading'; text: LocalText };

export type TextBlock = { id: string; type: 'text'; text: LocalText };

export type Swatch = { name: LocalText; hex: string; note: LocalText };

export type ColoursBlock = { id: string; type: 'colours'; swatches: Swatch[] };

export type TypeScaleRow = {
    label: LocalText;
    sizes: Partial<Record<Script, number>>;
};

export type TypePairingBlock = {
    id: string;
    type: 'type_pairing';
    faces: FontFaceSpec[];
    scale: TypeScaleRow[];
    note: LocalText;
};

export type LogoItem = {
    assetId: string | null;
    caption: LocalText;
    background: 'light' | 'dark';
};

export type LogosBlock = { id: string; type: 'logos'; items: LogoItem[] };

export type DoDontItem = {
    kind: 'do' | 'dont';
    assetId: string | null;
    caption: LocalText;
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
    downloadUrl: string;
};

export type GuidelinePageSummary = {
    id: string;
    title: LocalText;
    published: boolean;
    hasChanges: boolean;
};

/** Which language a reader shows: a language code, or 'all' for every language. */
export type ReadingLanguage = string;
