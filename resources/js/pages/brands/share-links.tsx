import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, ChevronLeft, Copy, Link2, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import { AssetThumbnail } from '@/components/asset-thumbnail';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { show as showBrand } from '@/routes/workspaces/brands';
import { destroy, store } from '@/routes/workspaces/brands/share-links';
import type { AssetSummary, ShareLinkKind, ShareLinkSummary } from '@/types';

type Props = {
    brand: { id: string; name: string };
    links: ShareLinkSummary[];
    files: AssetSummary[];
    publishedPages: number;
    defaults: { kind: ShareLinkKind; days: number; maxDays: number };
};

export default function ShareLinks({
    brand,
    links,
    files,
    publishedPages,
    defaults,
}: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const created = usePage().flash.shareLink as
        | { name: string; url: string }
        | undefined;

    return (
        <>
            <Head title={t('Share :brand', { brand: brand.name })} />

            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Link
                    href={showBrand([workspace.slug, brand.id])}
                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ChevronLeft className="size-4" />
                    {brand.name}
                </Link>
                <Heading
                    title={t('Share :brand', { brand: brand.name })}
                    description={t(
                        'Send a read-only link to people without an account, like a printer or a journalist. Links always expire, and you can turn one off at any time.',
                    )}
                />

                {created && <NewLink name={created.name} url={created.url} />}

                <CreateForm
                    slug={workspace.slug}
                    brandId={brand.id}
                    files={files}
                    publishedPages={publishedPages}
                    defaults={defaults}
                />

                <section className="space-y-3">
                    <h2 className="font-medium">{t('Links')}</h2>
                    {links.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No links yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {links.map((link) => (
                                <LinkRow
                                    key={link.id}
                                    link={link}
                                    ids={[workspace.slug, brand.id, link.id]}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

function NewLink({ name, url }: { name: string; url: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const copy = () => {
        void navigator.clipboard?.writeText(url).then(() => setCopied(true));
    };

    return (
        <section className="flex flex-col gap-3 rounded-xl border border-primary p-4">
            <p className="font-medium">
                {t(
                    '“:name” is ready. Copy the link now: for safety it is only shown once.',
                    {
                        name,
                    },
                )}
            </p>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    readOnly
                    value={url}
                    aria-label={t('Link')}
                    onFocus={(event) => event.currentTarget.select()}
                />
                <Button type="button" onClick={copy} className="shrink-0">
                    {copied ? <Check /> : <Copy />}
                    {copied ? t('Copied') : t('Copy link')}
                </Button>
            </div>
        </section>
    );
}

function CreateForm({
    slug,
    brandId,
    files,
    publishedPages,
    defaults,
}: {
    slug: string;
    brandId: string;
    files: AssetSummary[];
    publishedPages: number;
    defaults: Props['defaults'];
}) {
    const { t } = useTranslation();
    const [kind, setKind] = useState<ShareLinkKind>(defaults.kind);

    return (
        <section className="rounded-xl border p-4">
            <h2 className="mb-4 font-medium">{t('New link')}</h2>
            <Form
                {...store.form([slug, brandId])}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-4 sm:grid-cols-2"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="share-name">{t('Name')}</Label>
                            <Input
                                id="share-name"
                                name="name"
                                required
                                maxLength={120}
                                placeholder={t(
                                    'e.g. Logo pack for Addis Print House',
                                )}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <fieldset className="grid gap-2 sm:col-span-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t('What to share')}
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <KindOption
                                    value="guidelines"
                                    current={kind}
                                    onChange={setKind}
                                    title={t('Published guidelines')}
                                    description={
                                        publishedPages > 0
                                            ? t(
                                                  ':count published pages, with the files they show',
                                                  { count: publishedPages },
                                              )
                                            : t('No pages are published yet')
                                    }
                                />
                                <KindOption
                                    value="files"
                                    current={kind}
                                    onChange={setKind}
                                    title={t('A kit of files')}
                                    description={t(
                                        'Approved files you choose below',
                                    )}
                                />
                            </div>
                            <InputError message={errors.kind} />
                        </fieldset>

                        {kind === 'files' && (
                            <fieldset className="grid gap-2 sm:col-span-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('Files')}
                                </legend>
                                {files.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'This brand has no approved files yet. Only approved files can be shared.',
                                        )}
                                    </p>
                                ) : (
                                    <ul className="grid max-h-96 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-4">
                                        {files.map((file) => (
                                            <li key={file.id}>
                                                <label className="flex cursor-pointer flex-col overflow-hidden rounded-lg border has-checked:border-primary has-checked:ring-2 has-checked:ring-primary/30">
                                                    <AssetThumbnail
                                                        previewUrl={
                                                            file.previewUrl
                                                        }
                                                        kind={file.kind}
                                                        extension={
                                                            file.extension
                                                        }
                                                        alt=""
                                                        className="aspect-[4/3] w-full"
                                                    />
                                                    <span className="flex items-center gap-2 p-2 text-sm">
                                                        <input
                                                            type="checkbox"
                                                            name="asset_ids[]"
                                                            value={file.id}
                                                            className="size-4 accent-primary"
                                                        />
                                                        <span className="truncate">
                                                            {file.name}
                                                        </span>
                                                    </span>
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <InputError message={errors.asset_ids} />
                            </fieldset>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="share-days">
                                {t('Works for (days)')}
                            </Label>
                            <Input
                                id="share-days"
                                name="days"
                                type="number"
                                min={1}
                                max={defaults.maxDays}
                                defaultValue={defaults.days}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('At most :days days.', {
                                    days: defaults.maxDays,
                                })}
                            </p>
                            <InputError message={errors.days} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="share-password">
                                {t('Password (optional)')}
                            </Label>
                            <PasswordInput
                                id="share-password"
                                name="password"
                                autoComplete="new-password"
                                minLength={6}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('Send it separately from the link.')}
                            </p>
                            <InputError message={errors.password} />
                        </div>

                        <label className="flex items-start gap-3 sm:col-span-2">
                            <input
                                type="checkbox"
                                name="allow_originals"
                                value="1"
                                className="mt-1 size-4 accent-primary"
                            />
                            <span className="grid gap-1">
                                <span className="text-sm font-medium">
                                    {t('Allow downloading the original files')}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {t(
                                        'Off means previews only, which also loads faster on slow connections. Turn it on for printers who need the real files. Brand typefaces only show on shared guidelines when this is on.',
                                    )}
                                </span>
                            </span>
                        </label>

                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner /> : <Link2 />}
                                {t('Create link')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

function KindOption({
    value,
    current,
    onChange,
    title,
    description,
}: {
    value: ShareLinkKind;
    current: ShareLinkKind;
    onChange: (kind: ShareLinkKind) => void;
    title: string;
    description: string;
}) {
    return (
        <label
            className={cn(
                'flex cursor-pointer items-start gap-3 rounded-lg border p-3',
                current === value && 'border-primary ring-2 ring-primary/30',
            )}
        >
            <input
                type="radio"
                name="kind"
                value={value}
                checked={current === value}
                onChange={() => onChange(value)}
                className="mt-1 size-4 accent-primary"
            />
            <span className="grid gap-1">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-xs text-muted-foreground">
                    {description}
                </span>
            </span>
        </label>
    );
}

function LinkRow({
    link,
    ids,
}: {
    link: ShareLinkSummary;
    ids: [string, string, string];
}) {
    const { t, locale } = useTranslation();
    const date = (value: string) =>
        new Date(value).toLocaleDateString(locale, { dateStyle: 'medium' });

    return (
        <li className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 flex-col gap-1">
                <p className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">{link.name}</span>
                    {link.isLive ? (
                        <Badge variant="secondary">{t('Working')}</Badge>
                    ) : (
                        <Badge variant="outline">
                            {link.revokedAt ? t('Turned off') : t('Expired')}
                        </Badge>
                    )}
                    {link.hasPassword && (
                        <LockKeyhole
                            className="size-4 text-muted-foreground"
                            aria-label={t('Has a password')}
                        />
                    )}
                </p>
                <p className="text-sm text-muted-foreground">
                    {link.kind === 'guidelines'
                        ? t('Published guidelines')
                        : t(':count files', { count: link.files })}
                    {' · '}
                    {link.allowOriginals
                        ? t('originals allowed')
                        : t('previews only')}
                    {' · '}
                    {link.revokedAt
                        ? t('turned off :date', { date: date(link.revokedAt) })
                        : t('works until :date', {
                              date: date(link.expiresAt),
                          })}
                </p>
                <p className="text-sm text-muted-foreground">
                    {t('Opened :opens times, :downloads downloads', {
                        opens: link.opens,
                        downloads: link.downloads,
                    })}
                    {link.createdBy &&
                        ` · ${t('made by :name', { name: link.createdBy })}`}
                </p>
            </div>
            {link.canRevoke && link.isLive && (
                <Form {...destroy.form(ids)} options={{ preserveScroll: true }}>
                    {({ processing }) => (
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            {t('Turn off')}
                        </Button>
                    )}
                </Form>
            )}
        </li>
    );
}
