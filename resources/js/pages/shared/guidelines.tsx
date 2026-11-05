import { Head, Link } from '@inertiajs/react';
import {
    BlockView,
    Local,
    ReadingProvider,
} from '@/components/guidelines/block-view';
import {
    LanguageToggle,
    useReadingLanguage,
} from '@/components/guidelines/language-toggle';
import { pageTitle, useGuidelineFonts } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { SharedHeading } from '@/pages/shared/files';
import { page as sharedPage, show } from '@/routes/share';
import type {
    ContentLanguage,
    GuidelineAsset,
    GuidelineBlock,
    LocalText,
    SharedLink,
} from '@/types';

type Props = {
    link: SharedLink;
    languages: ContentLanguage[];
    pages: { id: string; title: LocalText }[];
    page: {
        id: string;
        title: LocalText;
        blocks: GuidelineBlock[];
        publishedAt: string | null;
    } | null;
    assets: Record<string, GuidelineAsset>;
};

export default function SharedGuidelines({
    link,
    languages,
    pages,
    page,
    assets,
}: Props) {
    const { t } = useTranslation();
    const [language, setLanguage] = useReadingLanguage(languages);

    useGuidelineFonts(page?.blocks ?? [], assets);

    const index = page ? pages.findIndex((p) => p.id === page.id) : -1;
    const previous = index > 0 ? pages[index - 1] : null;
    const next =
        index >= 0 && index < pages.length - 1 ? pages[index + 1] : null;
    const href = (id: string, position: number) =>
        position === 0 ? show(link.token) : sharedPage([link.token, id]);

    return (
        <>
            <Head
                title={
                    page
                        ? `${pageTitle(page.title, t, languages)} · ${link.brand.name}`
                        : link.name
                }
            />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <SharedHeading link={link} />

                {page === null ? (
                    <p className="text-muted-foreground">
                        {t('There is nothing in this link any more.')}
                    </p>
                ) : (
                    <div className="flex flex-col gap-6 lg:flex-row">
                        <nav
                            aria-label={t('Pages')}
                            className="flex shrink-0 flex-col gap-1 lg:w-56 print:hidden"
                        >
                            {pages.map((candidate, position) => (
                                <Link
                                    key={candidate.id}
                                    href={href(candidate.id, position)}
                                    className={cn(
                                        'rounded-md px-3 py-2 text-sm hover:bg-muted',
                                        candidate.id === page.id &&
                                            'bg-muted font-medium',
                                    )}
                                >
                                    {pageTitle(candidate.title, t, languages)}
                                </Link>
                            ))}
                        </nav>

                        <ReadingProvider
                            language={language}
                            languages={languages}
                        >
                            <article className="flex min-w-0 flex-1 flex-col gap-6">
                                <div className="print:hidden">
                                    <LanguageToggle
                                        value={language}
                                        onChange={setLanguage}
                                        languages={languages}
                                    />
                                </div>

                                <Local
                                    text={page.title}
                                    as="h2"
                                    className="border-b pb-4 text-4xl font-semibold tracking-tight"
                                    ethiopicClassName="font-medium tracking-normal"
                                />

                                {page.blocks.map((block) => (
                                    <BlockView
                                        key={block.id}
                                        block={block}
                                        assets={assets}
                                    />
                                ))}

                                <nav
                                    className="flex justify-between gap-4 border-t pt-4 text-sm print:hidden"
                                    aria-label={t('Pages')}
                                >
                                    {previous ? (
                                        <Link
                                            href={href(previous.id, index - 1)}
                                            className="hover:text-primary"
                                        >
                                            ←{' '}
                                            {pageTitle(
                                                previous.title,
                                                t,
                                                languages,
                                            )}
                                        </Link>
                                    ) : (
                                        <span />
                                    )}
                                    {next && (
                                        <Link
                                            href={href(next.id, index + 1)}
                                            className="hover:text-primary"
                                        >
                                            {pageTitle(
                                                next.title,
                                                t,
                                                languages,
                                            )}{' '}
                                            →
                                        </Link>
                                    )}
                                </nav>
                            </article>
                        </ReadingProvider>
                    </div>
                )}
            </div>
        </>
    );
}
