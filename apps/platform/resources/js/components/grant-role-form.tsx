import { useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { roleName } from '@/lib/roles';
import type { OrganisationType, RoleName } from '@/types/auth';

export type RoleOption = {
    role: RoleName;
    organisationTypes: OrganisationType[];
};

export type OrganisationOption = {
    id: number;
    name: string;
    type: OrganisationType;
};

type Props = {
    roleOptions: RoleOption[];
    organisations: OrganisationOption[];
    onSubmit: (role: RoleName, organisationId: number) => void;
    processing?: boolean;
    error?: string;
};

const selectClass =
    'h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50';

/**
 * Give a person a role. Only organisations of the right kind are offered
 * (a broker in a brokerage, an instructor in a school).
 */
export function GrantRoleForm({
    roleOptions,
    organisations,
    onSubmit,
    processing = false,
    error,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const [role, setRole] = useState<RoleName | ''>('');
    const [organisationId, setOrganisationId] = useState('');

    const allowed = useMemo(() => {
        const option = roleOptions.find((o) => o.role === role);

        return option
            ? organisations.filter((o) =>
                  option.organisationTypes.includes(o.type),
              )
            : [];
    }, [role, roleOptions, organisations]);

    return (
        <form
            className="space-y-4"
            onSubmit={(event) => {
                event.preventDefault();

                if (role !== '' && organisationId !== '') {
                    onSubmit(role, Number(organisationId));
                }
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${id}-role`}>
                        {t('people.grant.role')}
                    </Label>
                    <select
                        id={`${id}-role`}
                        className={selectClass}
                        value={role}
                        disabled={processing}
                        onChange={(event) => {
                            setRole(event.target.value as RoleName | '');
                            setOrganisationId('');
                        }}
                    >
                        <option value="">{t('people.grant.chooseRole')}</option>
                        {roleOptions.map((option) => (
                            <option key={option.role} value={option.role}>
                                {roleName(t, option.role)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${id}-organisation`}>
                        {t('people.grant.organisation')}
                    </Label>
                    <select
                        id={`${id}-organisation`}
                        className={selectClass}
                        value={organisationId}
                        disabled={processing || allowed.length === 0}
                        onChange={(event) =>
                            setOrganisationId(event.target.value)
                        }
                    >
                        <option value="">
                            {t('people.grant.chooseOrganisation')}
                        </option>
                        {allowed.map((organisation) => (
                            <option
                                key={organisation.id}
                                value={organisation.id}
                            >
                                {organisation.type === 'platform'
                                    ? `${organisation.name} · ${t('roles.organisationTypes.platform')}`
                                    : organisation.name}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {role !== '' && allowed.length === 0 ? (
                <p className="text-body text-muted-foreground">
                    {t('people.grant.noOrganisations')}
                </p>
            ) : null}

            <InputError message={error} />

            <Button
                type="submit"
                disabled={processing || role === '' || organisationId === ''}
            >
                {t('people.grant.submit')}
            </Button>
        </form>
    );
}
