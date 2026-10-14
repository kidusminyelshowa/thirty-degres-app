import { Form, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/workspaces/brands';

/**
 * Create a brand, or a sub-brand under `parent`.
 */
export function NewBrandDialog({
    parent,
}: {
    parent?: { id: string; name: string };
}) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={parent ? 'outline' : 'default'}>
                    <Plus />
                    {parent ? t('Sub-brand') : t('New brand')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {parent
                            ? t('New sub-brand of :brand', {
                                  brand: parent.name,
                              })
                            : t('New brand')}
                    </DialogTitle>
                    <DialogDescription>
                        {parent
                            ? t(
                                  'Everyone with a role on :brand gets the same role here.',
                                  {
                                      brand: parent.name,
                                  },
                              )
                            : t(
                                  'A brand holds its own files. You choose who works on it.',
                              )}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form(workspace.slug)}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {parent && (
                                <input
                                    type="hidden"
                                    name="parent_id"
                                    value={parent.id}
                                />
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="new-brand-name">
                                    {t('Name')}
                                </Label>
                                <Input
                                    id="new-brand-name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    placeholder={
                                        parent
                                            ? 'telebirr Merchant'
                                            : 'telebirr'
                                    }
                                />
                                <InputError
                                    message={errors.name ?? errors.parent_id}
                                />
                            </div>
                            <DialogFooter>
                                <Button disabled={processing}>
                                    {t('Create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
