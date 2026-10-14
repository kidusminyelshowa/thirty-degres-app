import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    Pencil,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AlertError from '@/components/alert-error';
import { LocalField, BlockEditor } from '@/components/guidelines/block-editor';
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
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    blockLabel,
    blockTypes,
    newBlock,
    pageTitle,
    useGuidelineFonts,
} from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { destroy, show, update } from '@/routes/workspaces/guidelines';
import type {
    ContentLanguage,
    GuidelineAsset,
    GuidelineBlock,
    GuidelinePageSummary,
    LocalText,
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
    library: GuidelineAsset[];
    editedBy: string | null;
    editedAt: string | null;
    can: {
        update: boolean;
        publish: boolean;
        publishReason: string | null;
        reorder: boolean;
        delete: boolean;
        create: boolean;
        setLanguages: boolean;
    };
};

export default function EditGuidelinePage({
    page,
    brand,
    languages,
    allLanguages,
    assets,
    library,
    editedBy,
    editedAt,
    can,
}: Props) {
    const { t, locale } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const errors = usePage().props.errors as Record<string, string>;
    const ids: [string, string] = [workspace.slug, page.id];

    const [title, setTitle] = useState<LocalText>(page.title);
    const [blocks, setBlocks] = useState<GuidelineBlock[]>(page.blocks);
    const [saved, setSaved] = useState(() =>
        JSON.stringify([page.title, page.blocks]),
    );
    const [saving, setSaving] = useState(false);
    const [mode, setMode] = useState<'edit' | 'preview'>('edit');
    const [language, setLanguage] = useReadingLanguage(languages);

    const dirty = JSON.stringify([title, blocks]) !== saved;

    // Every file in the library can be previewed while editing, including
    // ones picked since the last save.
    const allAssets = useMemo(
        () => ({
            ...assets,
            ...Object.fromEntries(library.map((asset) => [asset.id, asset])),
        }),
        [assets, library],
    );

    useGuidelineFonts(blocks, allAssets);

    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        const removeInertia = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (visit.method === 'get' && !visit.prefetch) {
                return window.confirm(
                    t('You have unsaved changes. Leave without saving?'),
                );
            }
        });

        window.addEventListener('beforeunload', warn);

        return () => {
            window.removeEventListener('beforeunload', warn);
            removeInertia();
        };
    }, [dirty, t]);

    const save = () => {
        router.put(
            update.url(ids),
            { title, blocks },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: (response) => {
                    const fresh = response.props.page as Props['page'];
                    setTitle(fresh.title);
                    setBlocks(fresh.blocks);
                    setSaved(JSON.stringify([fresh.title, fresh.blocks]));
                },
            },
        );
    };

    const setBlock = (index: number, block: GuidelineBlock) =>
        setBlocks(blocks.map((old, i) => (i === index ? block : old)));

    const moveBlock = (index: number, by: -1 | 1) => {
        const target = index + by;

        if (target < 0 || target >= blocks.length) {
            return;
        }

        const next = [...blocks];
        [next[index], next[target]] = [next[target], next[index]];
        setBlocks(next);
    };

    const messages = Object.values(errors ?? {});

    return (
        <>
            <Head title={`${t('Edit')}: ${pageTitle(title, t, languages)}`} />

            <div className="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:px-6">
                <div className="flex min-w-0 flex-col">
                    <Link
                        href={show(ids)}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        {brand.name} · {pageTitle(page.title, t, languages)}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {dirty
                            ? t('Unsaved changes')
                            : editedBy && editedAt
                              ? t('Last saved by :name on :date', {
                                    name: editedBy,
                                    date: new Date(editedAt).toLocaleString(
                                        locale,
                                        {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        },
                                    ),
                                })
                              : ''}
                    </span>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={mode}
                        onValueChange={(next) =>
                            next && setMode(next as 'edit' | 'preview')
                        }
                        aria-label={t('Mode')}
                    >
                        <ToggleGroupItem value="edit" className="px-3">
                            <Pencil />
                            {t('Edit')}
                        </ToggleGroupItem>
                        <ToggleGroupItem value="preview" className="px-3">
                            <Eye />
                            {t('Preview')}
                        </ToggleGroupItem>
                    </ToggleGroup>
                    {can.setLanguages && (
                        <LanguagesDialog
                            brand={brand}
                            languages={languages}
                            allLanguages={allLanguages}
                        />
                    )}
                    <Button onClick={save} disabled={!dirty || saving}>
                        <Save />
                        {saving ? t('Saving…') : t('Save')}
                    </Button>
                </div>
            </div>

            <ReadingProvider language={language} languages={languages}>
                <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-6">
                    {messages.length > 0 && (
                        <AlertError
                            errors={messages}
                            title={t('This page was not saved.')}
                        />
                    )}

                    {mode === 'preview' ? (
                        <article className="flex flex-col gap-6">
                            <LanguageToggle
                                value={language}
                                onChange={setLanguage}
                                languages={languages}
                            />
                            <header className="border-b pb-4">
                                <Local
                                    text={title}
                                    as="h1"
                                    className="text-4xl font-semibold tracking-tight"
                                    ethiopicClassName="font-medium tracking-normal"
                                />
                            </header>
                            {blocks.map((block) => (
                                <BlockView
                                    key={block.id}
                                    block={block}
                                    assets={allAssets}
                                />
                            ))}
                        </article>
                    ) : (
                        <>
                            <LocalField
                                label={t('Page title')}
                                value={title}
                                onChange={setTitle}
                                maxLength={120}
                            />

                            {blocks.map((block, index) => (
                                <section
                                    key={block.id}
                                    className="flex flex-col gap-3 rounded-xl border bg-card p-4"
                                    aria-label={blockLabel(t, block.type)}
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-muted-foreground">
                                            {blockLabel(t, block.type)}
                                        </span>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveBlock(index, -1)
                                                }
                                                aria-label={t('Move up')}
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    index === blocks.length - 1
                                                }
                                                onClick={() =>
                                                    moveBlock(index, 1)
                                                }
                                                aria-label={t('Move down')}
                                            >
                                                <ArrowDown />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setBlocks(
                                                        blocks.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                                aria-label={t('Remove block')}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </div>
                                    <BlockEditor
                                        block={block}
                                        onChange={(next) =>
                                            setBlock(index, next)
                                        }
                                        library={library}
                                    />
                                </section>
                            ))}

                            {blocks.length < 60 && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="w-fit"
                                        >
                                            <Plus />
                                            {t('Add block')}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        {blockTypes.map((type) => (
                                            <DropdownMenuItem
                                                key={type}
                                                onSelect={() =>
                                                    setBlocks([
                                                        ...blocks,
                                                        newBlock(
                                                            type,
                                                            languages,
                                                        ),
                                                    ])
                                                }
                                            >
                                                {blockLabel(t, type)}
                                            </DropdownMenuItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p className="text-sm text-muted-foreground">
                            {can.publish
                                ? t(
                                      'Saved changes go live when you publish from the page view.',
                                  )
                                : t(
                                      'Saved changes go live when a Brand manager publishes them.',
                                  )}
                        </p>
                        {can.delete && (
                            <Form
                                {...destroy.form(ids)}
                                onBefore={() =>
                                    window.confirm(
                                        t(
                                            'Delete this page? People on this brand will no longer see it.',
                                        ),
                                    )
                                }
                            >
                                <Button type="submit" variant="ghost">
                                    <Trash2 />
                                    {t('Delete page')}
                                </Button>
                            </Form>
                        )}
                    </div>
                </div>
            </ReadingProvider>
        </>
    );
}
