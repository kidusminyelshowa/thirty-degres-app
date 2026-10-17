import type { AssetKind, AssetStatus } from './asset';

/** What a share link page knows about the link it was opened from. */
export type SharedLink = {
    token: string;
    name: string;
    brand: { name: string };
    expiresAt: string;
    allowOriginals: boolean;
};

export type SharedFile = {
    id: string;
    name: string;
    kind: AssetKind;
    status: AssetStatus;
    extension: string;
    size: number;
    previewUrl: string | null;
    fontUrl: string | null;
    downloadUrl: string | null;
};

export type ShareLinkKind = 'guidelines' | 'files';

/** A brand's share link, as its Editors see it. */
export type ShareLinkSummary = {
    id: string;
    name: string;
    kind: ShareLinkKind;
    files: number;
    expiresAt: string;
    revokedAt: string | null;
    isLive: boolean;
    hasPassword: boolean;
    allowOriginals: boolean;
    createdBy: string | null;
    createdAt: string | null;
    lastOpenedAt: string | null;
    opens: number;
    downloads: number;
    canRevoke: boolean;
};
