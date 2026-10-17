import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Pencil,
    Printer,
    Share2,
} from 'lucide-react';
import {
    BlockView,
    Local,
    ReadingProvider,
} from '@/components/guidelines/block-view';
import {
    LanguageToggle,
    useReadingLanguage,
} from '@/components/guidelines/language-toggle';
import { LanguagesDialog } from '@/components/guidelines/languages-dialog';
import { NewPageDialog } from '@/components/guidelines/new-page-dialog';
import { PageNav } from '@/components/guidelines/page-nav';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { pageTitle, useGuidelineFonts } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { show as showBrand } from '@/routes/workspaces/brands';
import { index as brandShareLinks } from '@/routes/workspaces/brands/share-links';
import {
    edit,
    move,
    publish,
    show as showPage,
} from '@/routes/workspaces/guidelines';
import type {
    LocalText,
    ContentLanguage,
    GuidelineAsset,
    GuidelineBlock,
    GuidelinePageSummary,
} from '@/types';

type Props = {
    page: {
        id: string;
        title: LocalText;
        blocks: GuidelineBlock[];
        published: boolean;
        hasChanges: boolean;
    };
    brand: { id: string; name: string };
    languages: ContentLanguage[];
    allLanguages: ContentLanguage[];
    pages: GuidelinePageSummary[];
    assets: Record<string, GuidelineAsset>;
    publishedBy: string | null;
    publishedAt: string | null;
    can: {
        update: boolean;
        publish: boolean;
        publishReason: string | null;
        reorder: boolean;
        delete: boolean;
        create: boolean;
        setLanguages: boolean;
        share: boolean;
    };
};

export default function ShowGuidelinePage({
    page,
    brand,
    languages,
    allLanguages,
    pages,
    assets,
    publishedBy,
    publishedAt,
    can,
}: Props) {
    const { t, locale } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [language, setLanguage] = useReadingLanguage(languages);
    const ids: [string, string] = [workspace.slug, page.id];

    useGuidelineFonts(page.blocks, assets);

    const index = pages.findIndex((p) => p.id === page.id);
    const previous = index > 0 ? pages[index - 1] : null;
    const next =
        index >= 0 && index < pages.length - 1 ? pages[index + 1] : null;

    return (
        <>
            <Head
                title={`${pageTitle(page.title, t, languages)} · ${brand.name}`}
            />

            <div className="flex flex-col gap-6 p-4 md:p-6 lg:flex-row">
                <aside className="flex shrink-0 flex-col gap-3 lg:w-56 print:hidden">
                    <Link
                        href={showBrand([workspace.slug, brand.id])}
                        className="px-3 text-sm text-muted-foreground hover:text-foreground"
                    >
                        {brand.name}
                    </Link>
                    <PageNav
                        pages={pages}
                        current={page.id}
                        languages={languages}
                    />
                    {can.create && (
                        <NewPageDialog brand={brand} variant="outline" />
                    )}
                    {can.setLanguages && (
                        <LanguagesDialog
                            brand={brand}
                            languages={languages}
                            allLanguages={allLanguages}
                        />
                    )}
                </aside>

                <ReadingProvider language={language} languages={languages}>
                    <article className="flex min-w-0 flex-1 flex-col gap-6">
                        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                            <LanguageToggle
                                value={language}
                                onChange={setLanguage}
                                languages={languages}
                            />
                            <div className="flex flex-wrap gap-2">
                                {can.reorder && pages.length > 1 && (
                                    <>
                                        <Form
                                            {...move.form(ids)}
                                            options={{ preserveScroll: true }}
                                        >
                                            <input
                                                type="hidden"
                                                name="direction"
                                                value="up"
                                            />
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="icon"
                                                disabled={index <= 0}
                                                aria-label={t('Move page up')}
                                            >
                                                <ArrowUp />
                                            </Button>
                                        </Form>
                                        <Form
                                            {...move.form(ids)}
                                            options={{ preserveScroll: true }}
                                        >
                                            <input
                                                type="hidden"
                                                name="direction"
                                                value="down"
                                            />
                                            <Button
                                                type="submit"
                                                variant="ghost"
                                                size="icon"
                                                disabled={next === null}
                                                aria-label={t('Move page down')}
                                            >
                                                <ArrowDown />
                                            </Button>
                                        </Form>
                                    </>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => window.print()}
                                    aria-label={t('Print or save as PDF')}
                                >
                                    <Printer />
                                </Button>
                                {can.share && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={brandShareLinks(
                                                [workspace.slug, brand.id],
                                                {
                                                    query: {
                                                        kind: 'guidelines',
                                                    },
                                                },
                                            )}
                                        >
                                            <Share2 />
                                            {t('Share')}
                                        </Link>
                                    </Button>
                                )}
                                {can.update && (
                                    <Button variant="outline" asChild>
                                        <Link href={edit(ids)}>
                                            <Pencil />
                                            {t('Edit')}
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>

                        {!page.published && (
                            <div className="rounded-lg border border-dashed p-3 text-sm text-muted-foreground print:hidden">
                                {t(
                                    'Draft. Only people who can edit this page see it until a Brand manager publishes it.',
                                )}
                            </div>
                        )}

                        {page.published && page.hasChanges && (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3 text-sm print:hidden">
                                <span>
                                    {t(
                                        'This page has changes that are not published yet. Everyone else sees the version below.',
                                    )}
                                </span>
                                {can.publish ? (
                                    <PublishButton ids={ids} />
                                ) : (
                                    can.publishReason && (
                                        <span className="text-muted-foreground">
                                            {can.publishReason}
                                        </span>
                                    )
                                )}
                            </div>
                        )}

                        {!page.published &&
                            (can.publish || can.publishReason) && (
                                <div className="flex flex-wrap items-center gap-3 print:hidden">
                                    {can.publish ? (
                                        <PublishButton ids={ids} />
                                    ) : (
                                        <span className="text-sm text-muted-foreground">
                                            {can.publishReason}
                                        </span>
                                    )}
                                </div>
                            )}

                        <header className="flex flex-col gap-1 border-b pb-4">
                            <Local
                                text={page.title}
                                as="h1"
                                className="text-4xl font-semibold tracking-tight"
                                ethiopicClassName="font-medium tracking-normal"
                            />
                            <div className="flex flex-wrap items-center gap-2 pt-1 text-sm text-muted-foreground">
                                {page.published ? (
                                    <>
                                        <Badge variant="secondary">
                                            <CheckCircle2 />
                                            {t('Published')}
                                        </Badge>
                                        {publishedBy &&
                                            publishedAt &&
                                            t('by :name on :date', {
                                                name: publishedBy,
                                                date: new Date(
                                                    publishedAt,
                                                ).toLocaleDateString(locale, {
                                                    dateStyle: 'medium',
                                                }),
                                            })}
                                    </>
                                ) : (
                                    <Badge variant="outline">
                                        {t('Draft')}
                                    </Badge>
                                )}
                            </div>
                        </header>

                        {page.blocks.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t('This page is empty.')}
                            </p>
                        ) : (
                            page.blocks.map((block) => (
                                <BlockView
                                    key={block.id}
                                    block={block}
                                    assets={assets}
                                />
                            ))
                        )}

                        <nav
                            className="flex justify-between gap-4 border-t pt-4 text-sm print:hidden"
                            aria-label={t('Pages')}
                        >
                            {previous ? (
                                <Link
                                    href={showPage([
                                        workspace.slug,
                                        previous.id,
                                    ])}
                                    className="hover:text-primary"
                                >
                                    ← {pageTitle(previous.title, t, languages)}
                                </Link>
                            ) : (
                                <span />
                            )}
                            {next && (
                                <Link
                                    href={showPage([workspace.slug, next.id])}
                                    className="hover:text-primary"
                                >
                                    {pageTitle(next.title, t, languages)} →
                                </Link>
                            )}
                        </nav>
                    </article>
                </ReadingProvider>
            </div>
        </>
    );
}

function PublishButton({ ids }: { ids: [string, string] }) {
    const { t } = useTranslation();

    return (
        <Form {...publish.form(ids)} className="flex flex-col gap-1">
            {({ processing, errors }) => (
                <>
                    <Button type="submit" disabled={processing}>
                        <CheckCircle2 />
                        {t('Publish')}
                    </Button>
                    <InputError message={errors.publish} />
                </>
            )}
        </Form>
    );
}
