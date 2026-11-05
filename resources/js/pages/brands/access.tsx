import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { brandRoleDescription, brandRoleLabel } from '@/lib/assets';
import { useTranslation } from '@/lib/i18n';
import { kindLabel, roleLabel } from '@/lib/roles';
import { show as showBrand } from '@/routes/workspaces/brands';
import { destroy, store } from '@/routes/workspaces/brands/access';
import type { BrandRoleName, MembershipKind, WorkspaceRole } from '@/types';

type Person = {
    membershipId: string;
    name: string;
    email: string;
    kind: MembershipKind;
    partnerOrganisation: string | null;
    workspaceRole: WorkspaceRole;
    managesAll: boolean;
    role: BrandRoleName | null;
    roleId: string | null;
    inheritedRole: BrandRoleName | null;
    isYou: boolean;
};

type Props = {
    brand: {
        id: string;
        name: string;
        parent: { id: string; name: string } | null;
    };
    people: Person[];
};

const roles: BrandRoleName[] = ['manager', 'editor', 'viewer'];

export default function BrandAccess({ brand, people }: Props) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    return (
        <>
            <Head title={t('Access to :brand', { brand: brand.name })} />

            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <Link
                    href={showBrand([workspace.slug, brand.id])}
                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ChevronLeft className="size-4" />
                    {brand.name}
                </Link>
                <Heading
                    title={t('Who works on :brand', { brand: brand.name })}
                    description={
                        brand.parent
                            ? t(
                                  'Roles on :parent also apply here. You can give someone more here, never less.',
                                  {
                                      parent: brand.parent.name,
                                  },
                              )
                            : t(
                                  'Roles here also apply to this brand’s sub-brands.',
                              )
                    }
                />

                <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                    {roles.map((role) => (
                        <div key={role} className="contents">
                            <dt className="font-medium">
                                {brandRoleLabel(t, role)}
                            </dt>
                            <dd className="mb-2 text-muted-foreground sm:mb-0">
                                {brandRoleDescription(t, role)}
                            </dd>
                        </div>
                    ))}
                </dl>

                <ul className="divide-y rounded-xl border">
                    {people.map((person) => (
                        <li
                            key={person.membershipId}
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0">
                                <p className="truncate font-medium">
                                    {person.name}
                                    {person.isYou && (
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            ({t('you')})
                                        </span>
                                    )}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {person.email}
                                    {person.kind === 'partner' &&
                                        ` · ${kindLabel(t, person.kind)}, ${person.partnerOrganisation}`}
                                </p>
                            </div>
                            <PersonRole person={person} brandId={brand.id} />
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

function PersonRole({ person, brandId }: { person: Person; brandId: string }) {
    const { t } = useTranslation();
    const workspace = usePage().props.currentWorkspace!;

    if (person.managesAll) {
        return (
            <Badge variant="secondary">
                {t(':role, manages every brand', {
                    role: roleLabel(t, person.workspaceRole),
                })}
            </Badge>
        );
    }

    if (person.isYou) {
        return person.role || person.inheritedRole ? (
            <Badge variant="secondary">
                {brandRoleLabel(t, (person.role ?? person.inheritedRole)!)}
            </Badge>
        ) : null;
    }

    const choices = roles.filter(
        (role) => person.kind !== 'partner' || role !== 'manager',
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            {person.inheritedRole && !person.role && (
                <span className="text-sm text-muted-foreground">
                    {t(':role from the parent brand', {
                        role: brandRoleLabel(t, person.inheritedRole),
                    })}
                </span>
            )}
            <Form
                {...store.form([workspace.slug, brandId])}
                options={{ preserveScroll: true }}
                className="flex items-center gap-2"
            >
                {({ processing, errors, submit }) => (
                    <>
                        <input
                            type="hidden"
                            name="membership_id"
                            value={person.membershipId}
                        />
                        <Select
                            name="role"
                            value={person.role ?? undefined}
                            onValueChange={() => setTimeout(submit)}
                            disabled={processing}
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label={t('Role on this brand')}
                            >
                                <SelectValue placeholder={t('No access')} />
                            </SelectTrigger>
                            <SelectContent>
                                {choices.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        <span className="flex flex-col items-start">
                                            <span>
                                                {brandRoleLabel(t, role)}
                                            </span>
                                            <span className="text-xs text-muted-foreground in-data-[slot=select-value]:hidden">
                                                {brandRoleDescription(t, role)}
                                            </span>
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.role ?? errors.membership_id}
                        />
                    </>
                )}
            </Form>
            {person.roleId && (
                <Form
                    {...destroy.form([workspace.slug, brandId, person.roleId])}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button variant="ghost" size="sm" disabled={processing}>
                            {t('Remove')}
                        </Button>
                    )}
                </Form>
            )}
        </div>
    );
}
