import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { templateLabel, templates } from '@/lib/guidelines';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/workspaces/brands/guidelines';

/**
 * Start a guideline page from one of the starting points.
 */
export function NewPageDialog({
    brand,
    variant = 'default',
}: {
    brand: { id: string; name: string };
    variant?: 'default' | 'outline';
}) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={variant}>
                    <Plus />
                    {t('New page')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('New page for :brand', { brand: brand.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'Pick a starting point. You can change everything on it.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    {templates.map((template) => {
                        const label = templateLabel(t, template);

                        return (
                            <button
                                key={template}
                                type="button"
                                disabled={processing}
                                onClick={() =>
                                    router.post(
                                        store.url([workspace.slug, brand.id]),
                                        { template },
                                        {
                                            onStart: () => setProcessing(true),
                                            onFinish: () =>
                                                setProcessing(false),
                                            onError: (errors) =>
                                                setError(errors.template),
                                        },
                                    )
                                }
                                className="flex flex-col items-start gap-0.5 rounded-lg border p-3 text-left hover:border-ring hover:bg-muted disabled:opacity-60"
                            >
                                <span className="font-medium">
                                    {label.title}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {label.description}
                                </span>
                            </button>
                        );
                    })}
                    <InputError message={error} />
                </div>
            </DialogContent>
        </Dialog>
    );
}
