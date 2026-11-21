import { Head, Link, router } from '@inertiajs/react';
import { Download, Filter, Plus, SearchX, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { NewTenantDialog } from '@/components/new-tenant-dialog';
import { PageHeader } from '@/components/page-header';
import { TenantStatusBadge } from '@/components/tenant-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { nativeSelect, planLabel, tenantTypeLabel } from '@/lib/tenants';
import { toolInitials } from '@/lib/tool-initials';
import { exportMethod, index, show } from '@/routes/tenants';
import type { TenantFilters, TenantOptions, TenantRow } from '@/types';

type Props = {
    tenants: TenantRow[];
    filters: TenantFilters;
    options: TenantOptions;
};

const query = (filters: TenantFilters) =>
    Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) => value !== null && value !== '',
        ),
    );

/**
 * Tenants (super admin, web journey screen 59): every organisation with its
 * type, states, plan, people, sign-in domain, cell and status. Filter,
 * export, New tenant, and open one.
 */
export default function TenantsIndex({ tenants, filters, options }: Props) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState<TenantFilters>(filters);
    const [creating, setCreating] = useState(false);
    const stateName = (code: string) =>
        options.states.find((s) => s.code === code)?.name ?? code;
    const filtered = Object.values(query(filters)).length > 0;

    const apply = (next: TenantFilters) =>
        router.get(index().url, query(next), { preserveScroll: true });

    return (
        <>
            <Head title={t('tenants.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('tenants.title')}
                    description={t('tenants.description')}
                    actions={
                        <>
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button variant="outline">
                                        <Filter
                                            aria-hidden
                                            className="size-5"
                                        />
                                        {t('tenants.filter')}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="end"
                                    className="w-80 space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="tenant-search">
                                            {t('tenants.search')}
                                        </Label>
                                        <Input
                                            id="tenant-search"
                                            type="search"
                                            value={draft.search ?? ''}
                                            onChange={(e) =>
                                                setDraft({
                                                    ...draft,
                                                    search: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    {(
                                        [
                                            [
                                                'type',
                                                options.types,
                                                (v: string) =>
                                                    tenantTypeLabel(t, v),
                                            ],
                                            [
                                                'status',
                                                options.statuses,
                                                (v: string) =>
                                                    t(
                                                        `tenants.statuses.${v as TenantRow['status']}`,
                                                    ),
                                            ],
                                            [
                                                'state',
                                                options.states.map(
                                                    (s) => s.code,
                                                ),
                                                stateName,
                                            ],
                                        ] as const
                                    ).map(([key, values, label]) => (
                                        <div key={key} className="grid gap-2">
                                            <Label
                                                htmlFor={`tenant-filter-${key}`}
                                            >
                                                {t(`tenants.columns.${key}`)}
                                            </Label>
                                            <select
                                                id={`tenant-filter-${key}`}
                                                className={nativeSelect}
                                                value={draft[key] ?? ''}
                                                onChange={(e) =>
                                                    setDraft({
                                                        ...draft,
                                                        [key]:
                                                            e.target.value ===
                                                            ''
                                                                ? null
                                                                : e.target
                                                                      .value,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    {t('tenants.all')}
                                                </option>
                                                {values.map((value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label(value)}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    ))}
                                    <div className="flex justify-between gap-2">
                                        <Button
                                            variant="ghost"
                                            onClick={() => {
                                                const none = {
                                                    type: null,
                                                    status: null,
                                                    state: null,
                                                    search: null,
                                                };
                                                setDraft(none);
                                                apply(none);
                                            }}
                                        >
                                            {t('tenants.clear')}
                                        </Button>
                                        <Button onClick={() => apply(draft)}>
                                            {t('tenants.apply')}
                                        </Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                            <Button asChild variant="outline">
                                <a
                                    href={
                                        exportMethod({ query: query(filters) })
                                            .url
                                    }
                                >
                                    <Download aria-hidden className="size-5" />
                                    {t('tenants.export')}
                                </a>
                            </Button>
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden className="size-5" />
                                {t('tenants.newTenant')}
                            </Button>
                        </>
                    }
                />

                {tenants.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('tenants.emptyTitle')}
                        description={t('tenants.emptyBody')}
                        action={
                            filtered ? (
                                <Button
                                    onClick={() =>
                                        apply({
                                            type: null,
                                            status: null,
                                            state: null,
                                            search: null,
                                        })
                                    }
                                >
                                    {t('tenants.clear')}
                                </Button>
                            ) : (
                                <Button onClick={() => setCreating(true)}>
                                    {t('tenants.newTenant')}
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div className="rounded-reos-lg border border-border bg-card p-4">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[60rem] text-left text-sm">
                                <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        {(
                                            [
                                                'tenant',
                                                'type',
                                                'state',
                                                'plan',
                                                'users',
                                                'domain',
                                                'cell',
                                                'status',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className="px-3 py-3"
                                            >
                                                {t(`tenants.columns.${column}`)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {tenants.map((tenant) => (
                                        <tr
                                            key={tenant.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <th
                                                scope="row"
                                                className="px-3 py-3 font-semibold"
                                            >
                                                <Link
                                                    href={show(tenant.id)}
                                                    className="flex min-h-10 items-center gap-3 text-foreground hover:underline"
                                                >
                                                    <span
                                                        aria-hidden
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-reos-pill bg-accent text-xs font-bold text-primary"
                                                    >
                                                        {toolInitials(
                                                            tenant.name,
                                                        )}
                                                    </span>
                                                    {tenant.name}
                                                </Link>
                                            </th>
                                            <td className="px-3 py-3">
                                                {tenant.type === 'team' &&
                                                tenant.parent
                                                    ? t('tenants.teamIn', {
                                                          parent: tenant.parent,
                                                      })
                                                    : tenantTypeLabel(
                                                          t,
                                                          tenant.type,
                                                      )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {tenant.states
                                                    .map(stateName)
                                                    .join(', ') || '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {planLabel(t, tenant)}
                                            </td>
                                            <td className="px-3 py-3 tabular-nums">
                                                {tenant.members.toLocaleString()}
                                            </td>
                                            <td className="px-3 py-3">
                                                {tenant.domain ?? '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {tenant.cell}
                                            </td>
                                            <td className="px-3 py-3">
                                                <TenantStatusBadge
                                                    status={tenant.status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p className="mt-4 flex gap-3 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                            <ShieldCheck
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0"
                            />
                            {t('tenants.rlsNote')}
                        </p>
                    </div>
                )}
            </div>

            <NewTenantDialog
                open={creating}
                onOpenChange={setCreating}
                options={options}
            />
        </>
    );
}
