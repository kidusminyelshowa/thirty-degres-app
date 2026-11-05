import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { AssetThumbnail } from '@/components/asset-thumbnail';
import { Button } from '@/components/ui/button';
import { formatSize, kindLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import type { SharedFile, SharedLink } from '@/types';

type Props = {
    link: SharedLink;
    files: SharedFile[];
};

export default function SharedFiles({ link, files }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${link.name} · ${link.brand.name}`} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                <SharedHeading link={link} />

                {files.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('There is nothing in this link any more.')}
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {files.map((file) => (
                            <li
                                key={file.id}
                                className="flex flex-col overflow-hidden rounded-xl border bg-card"
                            >
                                <AssetThumbnail
                                    previewUrl={file.previewUrl}
                                    kind={file.kind}
                                    extension={file.extension}
                                    alt={file.name}
                                    className="aspect-[4/3] w-full"
                                />
                                <div className="flex flex-1 flex-col gap-2 p-3">
                                    <p
                                        className="truncate text-sm font-medium"
                                        title={file.name}
                                    >
                                        {file.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {kindLabel(t, file.kind)} ·{' '}
                                        <span className="uppercase">
                                            {file.extension}
                                        </span>{' '}
                                        · {formatSize(file.size)}
                                    </p>
                                    {file.downloadUrl && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="mt-auto"
                                        >
                                            <a
                                                href={file.downloadUrl}
                                                download
                                                aria-label={t(
                                                    'Download :name',
                                                    { name: file.name },
                                                )}
                                            >
                                                <Download aria-hidden />
                                                {t('Download')}
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

export function SharedHeading({ link }: { link: SharedLink }) {
    const { t, locale } = useTranslation();

    return (
        <header className="flex flex-col gap-1 border-b pb-4">
            <p className="text-sm text-muted-foreground">{link.brand.name}</p>
            <h1 className="text-2xl font-semibold tracking-tight">
                {link.name}
            </h1>
            <p className="text-sm text-muted-foreground">
                {t('This link works until :date.', {
                    date: new Date(link.expiresAt).toLocaleDateString(locale, {
                        dateStyle: 'medium',
                    }),
                })}{' '}
                {!link.allowOriginals &&
                    t(
                        'It shows previews only. Ask the sender if you need the original files.',
                    )}
            </p>
        </header>
    );
}
