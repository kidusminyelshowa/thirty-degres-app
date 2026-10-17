import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Search,
    Share2,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AssetCard } from '@/components/asset-card';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { UploadDialog } from '@/components/upload-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import {
    assetKinds,
    brandRoleLabel,
    kindLabel,
    statusLabel,
} from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { library } from '@/routes/workspaces';
import {
    show as showBrand,
    update as updateBrand,
} from '@/routes/workspaces/brands';
import { index as brandAccess } from '@/routes/workspaces/brands/access';
import { index as brandShareLinks } from '@/routes/workspaces/brands/share-links';
import { index as brandGuidelines } from '@/routes/workspaces/brands/guidelines';
import type {
    AssetKind,
    AssetStatus,
    AssetSummary,
    BrandRoleName,
    BrandSummary,
} from '@/types';
import { NewBrandDialog } from '@/components/new-brand-dialog';

type Filters = {
    q: string;
    tag: string;
    kind: AssetKind | null;
    status: AssetStatus | null;
    brand: string | null;
};

type BrandDetails = {
    id: string;
    name: string;
    parent: { id: string; name: string } | null;
    children: { id: string; name: string }[];
    role: BrandRoleName | null;
    canUpload: boolean;
    canRename: boolean;
    canManageAccess: boolean;
    canShare: boolean;
    canAddSubBrand: boolean;
};

type Props = {
    filters: Filters;
    assets: {
        data: AssetSummary[];
        currentPage: number;
        lastPage: number;
        total: number;
        nextPageUrl: string | null;
        previousPageUrl: string | null;
    };
    brands: BrandSummary[];
    tags: string[];
    brand: BrandDetails | null;
    canSeeArchived: boolean;
    canCreateBrand: boolean;
    upload: { maxMegabytes: number; extensions: string[] };
};

const ALL = 'all';

export default function Library({
    filters,
    assets,
    brands,
    tags,
    brand,
    canSeeArchived,
    upload,
}: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [query, setQuery] = useState(filters.q);

    const baseUrl = brand
        ? showBrand.url([workspace.slug, brand.id])
        : library.url(workspace.slug);

    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };

        router.get(
            baseUrl,
            Object.fromEntries(
                Object.entries({
                    q: next.q,
                    tag: next.tag,
                    kind: next.kind,
                    status: next.status,
                }).filter(([, value]) => value !== null && value !== ''),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const search = (event: FormEvent) => {
        event.preventDefault();
        visit({ q: query.trim() });
    };

    const filtering =
        filters.q !== '' ||
        filters.tag !== '' ||
        filters.kind !== null ||
        filters.status !== null;

    const title = brand ? brand.name : t('Library');

    return (
        <>
            <Head title={title} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        {brand?.parent && (
                            <Link
                                href={showBrand([
                                    workspace.slug,
                                    brand.parent.id,
                                ])}
                                className="text-sm text-muted-foreground hover:text-foreground"
                            >
                                {brand.parent.name}
                            </Link>
                        )}
                        <Heading
                            title={title}
                            description={
                                brand
                                    ? brand.role
                                        ? t('Your role: :role', {
                                              role: brandRoleLabel(
                                                  t,
                                                  brand.role,
                                              ),
                                          })
                                        : undefined
                                    : t(
                                          'Every file you can use, across your brands.',
                                      )
                            }
                        />
                    </div>
                    {brand && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link
                                    href={brandGuidelines([
                                        workspace.slug,
                                        brand.id,
                                    ])}
                                >
                                    <BookOpen />
                                    {t('Guidelines')}
                                </Link>
                            </Button>
                            {brand.canRename && (
                                <RenameBrandDialog brand={brand} />
                            )}
                            {brand.canManageAccess && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={brandAccess([
                                            workspace.slug,
                                            brand.id,
                                        ])}
                                    >
                                        <Users />
                                        {t('Access')}
                                    </Link>
                                </Button>
                            )}
                            {brand.canShare && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={brandShareLinks(
                                            [workspace.slug, brand.id],
                                            { query: { kind: 'files' } },
                                        )}
                                    >
                                        <Share2 />
                                        {t('Share')}
                                    </Link>
                                </Button>
                            )}
                            {brand.canAddSubBrand && (
                                <NewBrandDialog
                                    parent={{ id: brand.id, name: brand.name }}
                                />
                            )}
                            {brand.canUpload && (
                                <UploadDialog
                                    workspaceSlug={workspace.slug}
                                    brandId={brand.id}
                                    brandName={brand.name}
                                    maxMegabytes={upload.maxMegabytes}
                                    extensions={upload.extensions}
                                    tagSuggestions={tags}
                                />
                            )}
                        </div>
                    )}
                </div>

                {brand && brand.children.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-muted-foreground">
                            {t('Sub-brands')}
                        </span>
                        {brand.children.map((child) => (
                            <Button
                                key={child.id}
                                variant="outline"
                                size="sm"
                                asChild
                            >
                                <Link
                                    href={showBrand([workspace.slug, child.id])}
                                >
                                    {child.name}
                                </Link>
                            </Button>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-3">
                    <form
                        onSubmit={search}
                        className="flex gap-2"
                        role="search"
                    >
                        <div className="relative flex-1">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder={t('Search by name or tag')}
                                aria-label={t('Search by name or tag')}
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="secondary">
                            {t('Search')}
                        </Button>
                    </form>

                    <div className="flex flex-wrap gap-2">
                        {!brand && brands.length > 1 && (
                            <Select
                                value={ALL}
                                onValueChange={(id) =>
                                    id !== ALL &&
                                    router.get(
                                        showBrand.url([workspace.slug, id]),
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-auto min-w-36"
                                    aria-label={t('Brand')}
                                >
                                    <SelectValue
                                        placeholder={t('All brands')}
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {t('All brands')}
                                    </SelectItem>
                                    {brands.map((b) => (
                                        <SelectItem key={b.id} value={b.id}>
                                            {b.parentId
                                                ? `— ${b.name}`
                                                : b.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <Select
                            value={filters.kind ?? ALL}
                            onValueChange={(kind) =>
                                visit({
                                    kind:
                                        kind === ALL
                                            ? null
                                            : (kind as AssetKind),
                                })
                            }
                        >
                            <SelectTrigger
                                className="w-auto min-w-32"
                                aria-label={t('Type')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('All types')}
                                </SelectItem>
                                {assetKinds.map((kind) => (
                                    <SelectItem key={kind} value={kind}>
                                        {kindLabel(t, kind)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(status) =>
                                visit({
                                    status:
                                        status === ALL
                                            ? null
                                            : (status as AssetStatus),
                                })
                            }
                        >
                            <SelectTrigger
                                className="w-auto min-w-32"
                                aria-label={t('Status')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('Any status')}
                                </SelectItem>
                                <SelectItem value="approved">
                                    {statusLabel(t, 'approved')}
                                </SelectItem>
                                <SelectItem value="draft">
                                    {statusLabel(t, 'draft')}
                                </SelectItem>
                                {canSeeArchived && (
                                    <SelectItem value="archived">
                                        {statusLabel(t, 'archived')}
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        {filtering && (
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setQuery('');
                                    visit({
                                        q: '',
                                        tag: '',
                                        kind: null,
                                        status: null,
                                    });
                                }}
                            >
                                <X />
                                {t('Clear')}
                            </Button>
                        )}
                    </div>

                    {tags.length > 0 && (
                        <div
                            className="flex flex-wrap gap-1.5"
                            aria-label={t('Tags')}
                        >
                            {tags.map((tag) => {
                                const active =
                                    filters.tag.toLowerCase() ===
                                    tag.toLowerCase();

                                return (
                                    <button
                                        key={tag}
                                        type="button"
                                        onClick={() =>
                                            visit({ tag: active ? '' : tag })
                                        }
                                        aria-pressed={active}
                                    >
                                        <Badge
                                            variant={
                                                active ? 'default' : 'outline'
                                            }
                                            className="py-1 font-normal"
                                        >
                                            {tag}
                                        </Badge>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {assets.data.length === 0 ? (
                    <EmptyLibrary
                        filtering={filtering}
                        brand={brand}
                        brandCount={brands.length}
                    />
                ) : (
                    <>
                        <p className="text-sm text-muted-foreground">
                            {t('Files: :count', { count: assets.total })}
                        </p>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                            {assets.data.map((asset) => (
                                <AssetCard
                                    key={asset.id}
                                    asset={asset}
                                    showBrand={!brand}
                                />
                            ))}
                        </div>
                    </>
                )}

                {assets.lastPage > 1 && (
                    <nav className="flex items-center justify-center gap-3">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!assets.previousPageUrl}
                            onClick={() =>
                                assets.previousPageUrl &&
                                router.get(
                                    assets.previousPageUrl,
                                    {},
                                    { preserveState: true },
                                )
                            }
                        >
                            <ChevronLeft />
                            {t('Previous')}
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            {t('Page :page of :pages', {
                                page: assets.currentPage,
                                pages: assets.lastPage,
                            })}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!assets.nextPageUrl}
                            onClick={() =>
                                assets.nextPageUrl &&
                                router.get(
                                    assets.nextPageUrl,
                                    {},
                                    { preserveState: true },
                                )
                            }
                        >
                            {t('Next')}
                            <ChevronRight />
                        </Button>
                    </nav>
                )}
            </div>
        </>
    );
}

function EmptyLibrary({
    filtering,
    brand,
    brandCount,
}: {
    filtering: boolean;
    brand: BrandDetails | null;
    brandCount: number;
}) {
    const { t } = useTranslation();

    let message = t('No files yet.');

    if (filtering) {
        message = t('No files match. Try another word or tag.');
    } else if (brand?.canUpload) {
        message = t('No files yet. Upload the logo pack to start.');
    } else if (!brand && brandCount === 0) {
        message = t(
            'You have not been given access to a brand yet. Ask an Admin to add you.',
        );
    }

    return (
        <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
            {message}
        </div>
    );
}

function RenameBrandDialog({ brand }: { brand: BrandDetails }) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Pencil />
                    {t('Rename')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Rename brand')}</DialogTitle>
                </DialogHeader>
                <Form
                    {...updateBrand.form([workspace.slug, brand.id])}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="brand-name">{t('Name')}</Label>
                                <Input
                                    id="brand-name"
                                    name="name"
                                    defaultValue={brand.name}
                                    required
                                    maxLength={120}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <DialogFooter>
                                <Button disabled={processing}>
                                    {t('Save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
