import { Head, Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandTile } from '@/components/brand-tile';
import { CustomDomainCard } from '@/components/custom-domain-card';
import { InviteSheet } from '@/components/invite-sheet';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { roleName } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { show as branding } from '@/routes/branding';
import type {
    BrandProps,
    InviteOptions,
    TenantDomain,
    TenantType,
} from '@/types';
import type { RoleName } from '@/types/auth';

type Person = {
    id: number;
    name: string;
    role: RoleName;
    scope: string | null;
    two_step: boolean;
};

type Props = {
    tenant: {
        id: number;
        name: string;
        type: TenantType;
        address: string;
        live_address: string | null;
        seats: number | null;
        payer: 'organisation' | 'member';
    };
    domain: TenantDomain | null;
    brand: BrandProps;
    people: Person[];
    invite: InviteOptions;
};

const card = 'rounded-reos-lg border border-border bg-card p-5';

/**
 * Brokerage settings (broker, web journey screen 41): the brokerage's own
 * domain with its DNS records, the people who run it with their roles and
 * two-step status, and its plan. Its brand sits here too (decision of 21
 * November 2026), edited on Branding and domain. Invite admins opens the
 * invite sheet (A4.3a); changing permissions comes later.
 */
export default function BrokerageSettings({
    tenant,
    domain,
    brand,
    people,
    invite,
}: Props) {
    const { t } = useTranslation();
    const [inviting, setInviting] = useState(false);
    const seats =
        tenant.seats === null
            ? t('brokerageSettings.noLimit')
            : t('brokerageSettings.seats', { count: tenant.seats });

    return (
        <>
            <Head title={t('brokerageSettings.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerageSettings.title')}
                    description={t('brokerageSettings.purpose', {
                        name: tenant.name,
                    })}
                />

                <div className="grid gap-4 xl:grid-cols-2">
                    <CustomDomainCard
                        domain={domain}
                        address={tenant.address}
                    />

                    <section
                        className={cn(card, 'space-y-4')}
                        aria-labelledby="people-title"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="people-title" className="font-semibold">
                                {t('brokerageSettings.people')}
                            </h2>
                            <Button
                                variant="outline"
                                onClick={() => setInviting(true)}
                            >
                                <UserPlus aria-hidden className="size-5" />
                                {t('brokerageSettings.inviteAdmins')}
                            </Button>
                        </div>
                        {people.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t('brokerageSettings.noPeople')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[28rem] text-left text-sm">
                                    <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            {(
                                                [
                                                    'person',
                                                    'role',
                                                    'scope',
                                                    'twoStep',
                                                ] as const
                                            ).map((column) => (
                                                <th
                                                    key={column}
                                                    scope="col"
                                                    className="px-2 py-2"
                                                >
                                                    {t(
                                                        `brokerageSettings.columns.${column}`,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {people.map((person) => (
                                            <tr
                                                key={`${person.id}-${person.role}-${person.scope ?? ''}`}
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-2 py-2 font-medium"
                                                >
                                                    {person.name}
                                                </th>
                                                <td className="px-2 py-2">
                                                    {roleName(t, person.role)}
                                                </td>
                                                <td className="px-2 py-2">
                                                    {person.scope ??
                                                        t(
                                                            'brokerageSettings.everything',
                                                        )}
                                                </td>
                                                <td className="px-2 py-2">
                                                    <span
                                                        className={cn(
                                                            'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                                            person.two_step
                                                                ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                                : 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
                                                        )}
                                                    >
                                                        {person.two_step
                                                            ? t(
                                                                  'brokerageSettings.on',
                                                              )
                                                            : t(
                                                                  'brokerageSettings.off',
                                                              )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {t('brokerageSettings.peopleNote')}
                        </p>
                        <dl className="divide-y divide-border border-t border-border">
                            <div className="flex min-h-11 items-center justify-between gap-4 py-2">
                                <dt className="text-muted-foreground">
                                    {t('brokerageSettings.plan')}
                                </dt>
                                <dd className="font-semibold">
                                    {t('brokerageSettings.planValue', {
                                        payer: t(
                                            `brokerageSettings.payers.${tenant.payer}`,
                                        ),
                                        seats,
                                    })}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        className={cn(card, 'space-y-4')}
                        aria-labelledby="brand-title"
                    >
                        <h2 id="brand-title" className="font-semibold">
                            {t('brokerageSettings.brand')}
                        </h2>
                        <div className="flex flex-wrap items-center gap-4">
                            <BrandTile
                                name={tenant.name}
                                logoUrl={brand.logo_url}
                                colour={brand.primary}
                                className="size-14"
                            />
                            <dl className="min-w-0 flex-1 space-y-1">
                                {brand.primary || brand.app_label ? (
                                    <>
                                        <div className="flex flex-wrap gap-x-3">
                                            <dt className="text-muted-foreground">
                                                {t('brokerageSettings.colour')}
                                            </dt>
                                            <dd className="flex items-center gap-2 font-semibold">
                                                {brand.primary ? (
                                                    <span
                                                        aria-hidden
                                                        className="size-4 rounded-full"
                                                        style={{
                                                            backgroundColor:
                                                                brand.primary,
                                                        }}
                                                    />
                                                ) : null}
                                                {brand.primary ?? '—'}
                                            </dd>
                                        </div>
                                        <div className="flex flex-wrap gap-x-3">
                                            <dt className="text-muted-foreground">
                                                {t(
                                                    'brokerageSettings.appLabel',
                                                )}
                                            </dt>
                                            <dd className="font-semibold">
                                                {brand.app_label ?? '—'}
                                            </dd>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground">
                                        {t('brokerageSettings.noBrand')}
                                    </p>
                                )}
                            </dl>
                            <Button asChild variant="outline">
                                <Link href={branding()}>
                                    {t('brokerageSettings.editBrand')}
                                </Link>
                            </Button>
                        </div>
                    </section>
                </div>
            </div>

            <InviteSheet
                open={inviting}
                onOpenChange={setInviting}
                options={invite}
                tenant={tenant.name}
                defaultRole="broker"
            />
        </>
    );
}
