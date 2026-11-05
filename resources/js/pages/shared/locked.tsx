import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/lib/i18n';
import { unlock } from '@/routes/share';

type Props = {
    token: string;
    name: string;
    brand: { name: string };
};

export default function LockedShareLink({ token, name, brand }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={name} />
            <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-6 p-6">
                <div className="flex flex-col items-center gap-2 text-center">
                    <LockKeyhole className="size-8 text-muted-foreground" />
                    <h1 className="text-xl font-medium">{name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t(
                            ':brand shared this with a password. Enter it to continue.',
                            { brand: brand.name },
                        )}
                    </p>
                </div>
                <Form {...unlock.form(token)} className="grid gap-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="share-password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="share-password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="off"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('Open')}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
