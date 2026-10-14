import { Link, usePage } from '@inertiajs/react';
import { pageTitle } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { show } from '@/routes/workspaces/guidelines';
import type { ContentLanguage, GuidelinePageSummary } from '@/types';

/**
 * The brand's guideline pages, in order, with the open one marked.
 */
export function PageNav({
    pages,
    current,
    languages,
}: {
    pages: GuidelinePageSummary[];
    current: string;
    languages: ContentLanguage[];
}) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <nav aria-label={t('Guideline pages')}>
            <ol className="flex gap-1 overflow-x-auto lg:flex-col">
                {pages.map((page, index) => (
                    <li key={page.id} className="shrink-0">
                        <Link
                            href={show([workspace.slug, page.id])}
                            aria-current={
                                page.id === current ? 'page' : undefined
                            }
                            className={cn(
                                'flex items-baseline gap-2 rounded-md px-3 py-2 text-sm hover:bg-muted',
                                page.id === current && 'bg-muted font-medium',
                            )}
                        >
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {index + 1}
                            </span>
                            <span className="flex flex-col">
                                <span>
                                    {pageTitle(page.title, t, languages)}
                                </span>
                                {(!page.published || page.hasChanges) && (
                                    <span className="text-xs text-muted-foreground">
                                        {page.published
                                            ? t('Unpublished changes')
                                            : t('Draft')}
                                    </span>
                                )}
                            </span>
                        </Link>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
