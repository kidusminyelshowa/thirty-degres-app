import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { store } from '@/routes/workspaces';

export default function CreateWorkspace() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Create a workspace')} />
            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Company name')}</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoFocus
                                autoComplete="organization"
                                placeholder={t('e.g. Ethio Coffee')}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            {t('Create workspace')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

CreateWorkspace.layout = {
    title: 'Create a workspace',
    description:
        'A workspace holds one company’s brands, files and guidelines. Only the people you invite can see it.',
};
