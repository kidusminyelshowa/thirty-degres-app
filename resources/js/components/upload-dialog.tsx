import { router } from '@inertiajs/react';
import { Check, CircleAlert, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { TagInput } from '@/components/tag-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatSize } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { store } from '@/routes/workspaces/brands/assets';

type Item = {
    key: string;
    file: File;
    state: 'waiting' | 'uploading' | 'done' | 'failed';
    progress: number;
    error?: string;
    retryable: boolean;
};

/**
 * Pick or drop several files. They upload one at a time, so on a slow
 * connection a failure only means retrying that one file.
 */
export function UploadDialog({
    workspaceSlug,
    brandId,
    brandName,
    maxMegabytes,
    extensions,
    tagSuggestions,
}: {
    workspaceSlug: string;
    brandId: string;
    brandName: string;
    maxMegabytes: number;
    extensions: string[];
    tagSuggestions: string[];
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<Item[]>([]);
    const [tags, setTags] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [dragging, setDragging] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    const update = (key: string, change: Partial<Item>) =>
        setItems((current) =>
            current.map((item) =>
                item.key === key ? { ...item, ...change } : item,
            ),
        );

    const addFiles = (files: FileList | null) => {
        if (!files) {
            return;
        }

        const added = Array.from(files).map((file): Item => {
            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
            let error: string | undefined;

            if (!extensions.includes(extension)) {
                error = t('This type of file cannot be added to the library.');
            } else if (file.size > maxMegabytes * 1024 * 1024) {
                error = t('Larger than :size MB.', { size: maxMegabytes });
            }

            return {
                key: `${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
                file,
                state: error ? 'failed' : 'waiting',
                progress: 0,
                error,
                retryable: false,
            };
        });

        setItems((current) => [...current, ...added]);
    };

    const uploadOne = (item: Item) =>
        new Promise<void>((resolve) => {
            update(item.key, {
                state: 'uploading',
                progress: 0,
                error: undefined,
            });

            router.post(
                store.url([workspaceSlug, brandId]),
                { file: item.file, tags },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    preserveState: true,
                    onProgress: (event) =>
                        update(item.key, {
                            progress: Math.round(event?.percentage ?? 0),
                        }),
                    onSuccess: () =>
                        update(item.key, { state: 'done', progress: 100 }),
                    onError: (errors) =>
                        update(item.key, {
                            state: 'failed',
                            retryable: true,
                            error:
                                errors.file ??
                                Object.values(errors)[0] ??
                                t('Upload failed. Try again.'),
                        }),
                    onFinish: () => {
                        setItems((current) =>
                            current.map((held) =>
                                held.key === item.key &&
                                held.state === 'uploading'
                                    ? {
                                          ...held,
                                          state: 'failed',
                                          retryable: true,
                                          error: t('Upload failed. Try again.'),
                                      }
                                    : held,
                            ),
                        );
                        resolve();
                    },
                },
            );
        });

    const uploadAll = async () => {
        setBusy(true);

        for (const item of items) {
            if (item.state === 'waiting') {
                await uploadOne(item);
            }
        }

        setBusy(false);
    };

    const retry = async (item: Item) => {
        setBusy(true);
        await uploadOne(item);
        setBusy(false);
    };

    const waiting = items.filter((item) => item.state === 'waiting').length;
    const finished =
        items.length > 0 &&
        items.every(
            (item) => item.state === 'done' || item.state === 'failed',
        ) &&
        items.some((item) => item.state === 'done');

    const onDrop = (event: DragEvent<HTMLButtonElement>) => {
        event.preventDefault();
        setDragging(false);
        addFiles(event.dataTransfer.files);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (busy) {
                    return;
                }

                setOpen(next);

                if (!next) {
                    setItems([]);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button>
                    <Upload />
                    {t('Upload')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('Upload to :brand', { brand: brandName })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'New files are drafts until a Brand manager approves them. Up to :size MB each.',
                            { size: maxMegabytes },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                    disabled={busy}
                    className={cn(
                        'flex flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center text-sm text-muted-foreground transition-colors hover:border-primary',
                        dragging && 'border-primary bg-accent',
                    )}
                >
                    <Upload className="size-6" aria-hidden />
                    <span className="font-medium text-foreground">
                        {t('Choose files or drop them here')}
                    </span>
                    <span>
                        {t('Logos, fonts, photos, documents and zip packs')}
                    </span>
                </button>
                <input
                    ref={input}
                    type="file"
                    multiple
                    hidden
                    accept={extensions.map((e) => `.${e}`).join(',')}
                    onChange={(event) => {
                        addFiles(event.target.files);
                        event.target.value = '';
                    }}
                />

                {items.length > 0 && (
                    <ul className="divide-y rounded-lg border text-sm">
                        {items.map((item) => (
                            <li
                                key={item.key}
                                className="flex items-center gap-3 p-3"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate">{item.file.name}</p>
                                    <p
                                        className={cn(
                                            'text-xs text-muted-foreground',
                                            item.state === 'failed' &&
                                                'text-destructive',
                                        )}
                                    >
                                        {item.state === 'failed'
                                            ? item.error
                                            : item.state === 'uploading'
                                              ? `${item.progress}%`
                                              : formatSize(item.file.size)}
                                    </p>
                                </div>
                                {item.state === 'uploading' && <Spinner />}
                                {item.state === 'done' && (
                                    <Check
                                        className="size-4 text-primary"
                                        aria-label={t('Uploaded')}
                                    />
                                )}
                                {item.state === 'failed' &&
                                    (item.retryable ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={busy}
                                            onClick={() => retry(item)}
                                        >
                                            {t('Retry')}
                                        </Button>
                                    ) : (
                                        <CircleAlert
                                            className="size-4 text-destructive"
                                            aria-hidden
                                        />
                                    ))}
                            </li>
                        ))}
                    </ul>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="upload-tags">
                        {t('Tags for these files')}
                    </Label>
                    <TagInput
                        id="upload-tags"
                        value={tags}
                        onChange={setTags}
                        suggestions={tagSuggestions}
                    />
                </div>

                <DialogFooter>
                    {finished && waiting === 0 ? (
                        <Button onClick={() => setOpen(false)}>
                            {t('Done')}
                        </Button>
                    ) : (
                        <Button
                            onClick={uploadAll}
                            disabled={busy || waiting === 0}
                        >
                            {busy && <Spinner />}
                            {waiting > 0
                                ? t('Upload :count files', { count: waiting })
                                : t('Upload')}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
