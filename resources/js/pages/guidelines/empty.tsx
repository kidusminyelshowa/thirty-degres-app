import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { NewPageDialog } from '@/components/guidelines/new-page-dialog';
import { useTranslation } from '@/lib/i18n';
import { show as showBrand } from '@/routes/workspaces/brands';

type Props = {
    brand: { id: string; name: string };
    canCreate: boolean;
};

export default function EmptyGuidelines({ brand, canCreate }: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <>
            <Head title={`${t('Guidelines')} · ${brand.name}`} />

            <div className="flex flex-col items-center gap-4 p-10 text-center">
                <BookOpen
                    className="size-10 text-muted-foreground"
                    aria-hidden
                />
                <h1 className="text-2xl font-semibold">
                    {t(':brand has no guideline pages yet', {
                        brand: brand.name,
                    })}
                </h1>
                <p className="max-w-md text-muted-foreground">
                    {canCreate
                        ? t(
                              'Start with the logo, colour, type or voice page. Each one is written in Amharic and English.',
                          )
                        : t(
                              'When a Brand manager publishes the guidelines, you will find them here.',
                          )}
                </p>
                <div className="flex gap-2">
                    {canCreate && <NewPageDialog brand={brand} />}
                    <Link
                        href={showBrand([workspace.slug, brand.id])}
                        className="self-center text-sm text-muted-foreground hover:text-foreground"
                    >
                        {t('Back to the library')}
                    </Link>
                </div>
            </div>
        </>
    );
}
