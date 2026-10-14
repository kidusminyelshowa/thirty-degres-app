import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    CheckCircle2,
    Download,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { AssetThumbnail } from '@/components/asset-thumbnail';
import InputError from '@/components/input-error';
import { TagInput } from '@/components/tag-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { assetKinds, formatSize, kindLabel, statusLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import {
    approve,
    archive,
    destroy,
    restore,
    update,
} from '@/routes/workspaces/assets';
import { show as showBrand } from '@/routes/workspaces/brands';
import type { AssetSummary } from '@/types';

type Props = {
    asset: AssetSummary & {
        originalName: string;
        width: number | null;
        height: number | null;
        previewStatus: 'none' | 'ready' | 'failed';
        uploadedBy: string | null;
        approvedBy: string | null;
        approvedAt: string | null;
        downloadUrl: string;
    };
    can: {
        update: boolean;
        approve: boolean;
        approveReason: string | null;
        archive: boolean;
        restore: boolean;
        delete: boolean;
    };
    tagSuggestions: string[];
};

export default function ShowAsset({ asset, can, tagSuggestions }: Props) {
    const { t, locale } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const ids: [string, string] = [workspace.slug, asset.id];

    const date = (iso: string | null) =>
        iso
            ? new Date(iso).toLocaleDateString(locale, { dateStyle: 'medium' })
            : '';

    return (
        <>
            <Head title={asset.name} />

            <div className="grid gap-6 p-4 md:p-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div className="flex flex-col gap-3">
                    <AssetThumbnail
                        previewUrl={asset.previewUrl}
                        kind={asset.kind}
                        extension={asset.extension}
                        alt={asset.name}
                        eager
                        className="aspect-[4/3] w-full rounded-xl border"
                    />
                    <p className="text-xs text-muted-foreground">
                        {asset.previewUrl
                            ? t(
                                  'This is a light preview. Download gets the original file.',
                              )
                            : t(
                                  'No preview for this type of file. Download it to open it.',
                              )}
                    </p>
                </div>

                <div className="flex flex-col gap-5">
                    <div className="flex flex-col gap-2">
                        <Link
                            href={showBrand([workspace.slug, asset.brand.id])}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            {asset.brand.name}
                        </Link>
                        <h1 className="text-xl font-semibold break-words">
                            {asset.name}
                        </h1>
                        <div className="flex flex-wrap gap-2">
                            <Badge
                                variant={
                                    asset.status === 'approved'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {statusLabel(t, asset.status)}
                            </Badge>
                            <Badge variant="outline">
                                {kindLabel(t, asset.kind)}
                            </Badge>
                        </div>
                    </div>

                    <Button asChild size="lg" className="w-full sm:w-auto">
                        {/* A plain link: the browser saves the file itself. */}
                        <a href={asset.downloadUrl} download>
                            <Download />
                            {t('Download original')}
                            <span className="opacity-80">
                                ({asset.extension.toUpperCase()},{' '}
                                {formatSize(asset.size)})
                            </span>
                        </a>
                    </Button>

                    <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                        <dt className="text-muted-foreground">{t('File')}</dt>
                        <dd className="break-all">{asset.originalName}</dd>
                        {asset.width && asset.height && (
                            <>
                                <dt className="text-muted-foreground">
                                    {t('Size')}
                                </dt>
                                <dd>
                                    {asset.width} × {asset.height} px
                                </dd>
                            </>
                        )}
                        <dt className="text-muted-foreground">
                            {t('Uploaded')}
                        </dt>
                        <dd>
                            {asset.uploadedBy ?? t('Former member')},{' '}
                            {date(asset.createdAt)}
                        </dd>
                        {asset.approvedAt && (
                            <>
                                <dt className="text-muted-foreground">
                                    {t('Approved')}
                                </dt>
                                <dd>
                                    {asset.approvedBy ?? t('Former member')},{' '}
                                    {date(asset.approvedAt)}
                                </dd>
                            </>
                        )}
                    </dl>

                    {!can.update && asset.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {asset.tags.map((tag) => (
                                <Badge
                                    key={tag}
                                    variant="outline"
                                    className="font-normal"
                                >
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}

                    <StatusActions ids={ids} asset={asset} can={can} />

                    {can.update && (
                        <EditDetails
                            ids={ids}
                            asset={asset}
                            tagSuggestions={tagSuggestions}
                        />
                    )}
                </div>
            </div>
        </>
    );
}

function StatusActions({
    ids,
    asset,
    can,
}: {
    ids: [string, string];
    asset: Props['asset'];
    can: Props['can'];
}) {
    const { t } = useTranslation();

    if (
        !can.approve &&
        !can.archive &&
        !can.restore &&
        !can.delete &&
        !can.approveReason
    ) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3 rounded-xl border p-4">
            {asset.status === 'draft' && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Drafts are only seen by Editors and Brand managers until approved.',
                    )}
                </p>
            )}
            {can.approveReason && (
                <p className="text-sm">{can.approveReason}</p>
            )}
            <div className="flex flex-wrap gap-2">
                {can.approve && (
                    <Form {...approve.form(ids)}>
                        {({ processing }) => (
                            <Button disabled={processing}>
                                <CheckCircle2 />
                                {t('Approve')}
                            </Button>
                        )}
                    </Form>
                )}
                {can.archive && (
                    <Form {...archive.form(ids)}>
                        {({ processing }) => (
                            <Button variant="outline" disabled={processing}>
                                <Archive />
                                {t('Archive')}
                            </Button>
                        )}
                    </Form>
                )}
                {can.restore && (
                    <Form {...restore.form(ids)}>
                        {({ processing }) => (
                            <Button variant="outline" disabled={processing}>
                                <ArchiveRestore />
                                {t('Restore')}
                            </Button>
                        )}
                    </Form>
                )}
                {can.delete && (
                    <Form
                        {...destroy.form(ids)}
                        onBefore={() =>
                            window.confirm(t('Delete this draft for good?'))
                        }
                    >
                        {({ processing }) => (
                            <Button
                                variant="ghost"
                                className="text-destructive"
                                disabled={processing}
                            >
                                <Trash2 />
                                {t('Delete draft')}
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </section>
    );
}

function EditDetails({
    ids,
    asset,
    tagSuggestions,
}: {
    ids: [string, string];
    asset: Props['asset'];
    tagSuggestions: string[];
}) {
    const { t } = useTranslation();
    const [tags, setTags] = useState(asset.tags);

    return (
        <section className="rounded-xl border p-4">
            <h2 className="mb-4 font-medium">{t('Details')}</h2>
            <Form
                {...update.form(ids)}
                options={{ preserveScroll: true }}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="asset-name">{t('Name')}</Label>
                            <Input
                                id="asset-name"
                                name="name"
                                defaultValue={asset.name}
                                required
                                maxLength={200}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="asset-kind">{t('Type')}</Label>
                            <Select name="kind" defaultValue={asset.kind}>
                                <SelectTrigger id="asset-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {assetKinds.map((kind) => (
                                        <SelectItem key={kind} value={kind}>
                                            {kindLabel(t, kind)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="asset-tags">{t('Tags')}</Label>
                            <TagInput
                                id="asset-tags"
                                value={tags}
                                onChange={setTags}
                                suggestions={tagSuggestions}
                            />
                            <InputError
                                message={errors.tags ?? errors['tags.0']}
                            />
                        </div>
                        <div>
                            <Button disabled={processing}>{t('Save')}</Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
