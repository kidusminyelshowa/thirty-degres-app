export type AssetKind = 'logo' | 'font' | 'photo' | 'document' | 'other';

export type AssetStatus = 'draft' | 'approved' | 'archived';

export type BrandRoleName = 'manager' | 'editor' | 'viewer';

export type BrandSummary = {
    id: string;
    name: string;
    parentId: string | null;
    role: BrandRoleName;
};

export type AssetSummary = {
    id: string;
    name: string;
    kind: AssetKind;
    status: AssetStatus;
    extension: string;
    size: number;
    previewUrl: string | null;
    brand: { id: string; name: string };
    tags: string[];
    createdAt: string | null;
};
