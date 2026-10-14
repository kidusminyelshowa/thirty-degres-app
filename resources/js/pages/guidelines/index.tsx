import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { LanguagesDialog } from '@/components/guidelines/languages-dialog';
import { NewPageDialog } from '@/components/guidelines/new-page-dialog';
import Heading from '@/components/heading';
import { pageTitle } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { show } from '@/routes/workspaces/guidelines';
import type { ContentLanguage, GuidelinePageSummary } from '@/types';

type Props = {
    brands: {
        id: string;
        name: string;
        parentId: string | null;
        languages: ContentLanguage[];
        pages: GuidelinePageSummary[];
        canCreate: boolean;
        canSetLanguages: boolean;
    }[];
    allLanguages: ContentLanguage[];
};

export default function GuidelinesIndex({ brands, allLanguages }: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <>
            <Head title={t('Guidelines')} />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title={t('Guidelines')}
                    description={t(
                        'How each brand looks and sounds, in each of its languages.',
                    )}
                />

                {brands.length === 0 && (
                    <p className="text-muted-foreground">
                        {t('You are not on any brands yet.')}
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {brands.map((brand) => (
                        <section
                            key={brand.id}
                            className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                        >
                            <h2 className="flex items-center gap-2 text-lg font-medium">
                                <BookOpen
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                {brand.parentId
                                    ? `— ${brand.name}`
                                    : brand.name}
                            </h2>
                            {brand.pages.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No guideline pages yet.')}
                                </p>
                            ) : (
                                <ol className="flex flex-col gap-1 text-sm">
                                    {brand.pages.map((page, index) => (
                                        <li key={page.id}>
                                            <Link
                                                href={show([
                                                    workspace.slug,
                                                    page.id,
                                                ])}
                                                className="flex items-baseline gap-2 hover:text-primary"
                                            >
                                                <span className="text-xs text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </span>
                                                <span>
                                                    {pageTitle(
                                                        page.title,
                                                        t,
                                                        brand.languages,
                                                    )}
                                                </span>
                                                {!page.published && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {t('Draft')}
                                                    </span>
                                                )}
                                            </Link>
                                        </li>
                                    ))}
                                </ol>
                            )}
                            <p className="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                                {brand.languages.map((language) => (
                                    <span
                                        key={language.code}
                                        lang={language.code}
                                        dir={language.dir}
                                    >
                                        {language.native}
                                    </span>
                                ))}
                            </p>
                            {(brand.canCreate || brand.canSetLanguages) && (
                                <div className="mt-auto flex flex-wrap gap-2 pt-2">
                                    {brand.canCreate && (
                                        <NewPageDialog
                                            brand={brand}
                                            variant="outline"
                                        />
                                    )}
                                    {brand.canSetLanguages && (
                                        <LanguagesDialog
                                            brand={brand}
                                            languages={brand.languages}
                                            allLanguages={allLanguages}
                                        />
                                    )}
                                </div>
                            )}
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}
