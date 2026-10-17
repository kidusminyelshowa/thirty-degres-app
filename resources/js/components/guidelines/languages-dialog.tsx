import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Languages, Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { useTranslation } from '@/lib/i18n';
import { languages as saveLanguages } from '@/routes/workspaces/brands/guidelines';
import type { ContentLanguage } from '@/types';

/**
 * Choose which languages a brand's guidelines are written in, and their
 * order. The first is shown when a text is missing in the reader's language.
 */
export function LanguagesDialog({
    brand,
    languages,
    allLanguages,
}: {
    brand: { id: string; name: string };
    languages: ContentLanguage[];
    allLanguages: ContentLanguage[];
}) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [open, setOpen] = useState(false);
    const [chosen, setChosen] = useState<string[]>(
        languages.map((l) => l.code),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    const byCode = Object.fromEntries(allLanguages.map((l) => [l.code, l]));
    const others = allLanguages.filter((l) => !chosen.includes(l.code));

    const move = (index: number, by: -1 | 1) => {
        const next = [...chosen];
        [next[index], next[index + by]] = [next[index + by], next[index]];
        setChosen(next);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setChosen(languages.map((l) => l.code));
                setError(undefined);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Languages />
                    {t('Languages')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('Languages for :brand', { brand: brand.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Every text on the guidelines can be written in these languages. The first one is shown when a text has not been written in the reader’s language.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                <ol className="flex flex-col gap-1">
                    {chosen.map((code, index) => (
                        <li
                            key={code}
                            className="flex items-center justify-between gap-2 rounded-md border px-3 py-1.5"
                        >
                            <span className="flex items-baseline gap-2">
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <span
                                    lang={code}
                                    dir={byCode[code]?.dir}
                                    className="font-medium"
                                >
                                    {byCode[code]?.native ?? code}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {byCode[code]?.name}
                                </span>
                            </span>
                            <span className="flex">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                    aria-label={t('Move up')}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    disabled={index === chosen.length - 1}
                                    onClick={() => move(index, 1)}
                                    aria-label={t('Move down')}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    disabled={chosen.length === 1}
                                    onClick={() =>
                                        setChosen(
                                            chosen.filter((c) => c !== code),
                                        )
                                    }
                                    aria-label={t('Remove')}
                                >
                                    <X />
                                </Button>
                            </span>
                        </li>
                    ))}
                </ol>

                {others.length > 0 && chosen.length < 8 && (
                    <div className="flex flex-wrap gap-2">
                        {others.map((language) => (
                            <Button
                                key={language.code}
                                variant="secondary"
                                size="sm"
                                onClick={() =>
                                    setChosen([...chosen, language.code])
                                }
                            >
                                <Plus />
                                <span lang={language.code} dir={language.dir}>
                                    {language.native}
                                </span>
                            </Button>
                        ))}
                    </div>
                )}

                <p className="text-sm text-muted-foreground">
                    {t(
                        'Taking a language off hides it but keeps what was written, so adding it back brings it back.',
                    )}
                </p>
                <InputError message={error} />

                <DialogFooter>
                    <Button
                        disabled={processing}
                        onClick={() =>
                            router.put(
                                saveLanguages.url([workspace.slug, brand.id]),
                                { languages: chosen },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                    onSuccess: () => setOpen(false),
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors).join(' '),
                                        ),
                                },
                            )
                        }
                    >
                        {t('Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
