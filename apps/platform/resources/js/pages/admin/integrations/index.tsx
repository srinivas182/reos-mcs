import { Head, Link } from '@inertiajs/react';
import { Info, SearchX } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CatalogueStatusBadge } from '@/components/catalogue-status-badge';
import { EmptyState } from '@/components/empty-state';
import { ModeBadge } from '@/components/integration-badges';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { roleName } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { demand, show } from '@/routes/integrations';
import type { CatalogueStatus, IntegrationSummary } from '@/types';
import type { Membership } from '@/types/auth';

type Props = { integrations: IntegrationSummary[] };

type Tab = 'all' | CatalogueStatus | 'platform';

const STATUS_TABS: CatalogueStatus[] = [
    'enabled',
    'beta',
    'coming_soon',
    'paused',
    'retired',
];

/**
 * Integrations catalogue (super admin, web journey screen 64): every tool
 * with its status, where and for whom it's available, how many accounts are
 * connected, its health and the "Notify me" demand. Platform services (keys
 * only, never connected by people) have their own tab.
 */
export default function IntegrationsIndex({ integrations }: Props) {
    const { t, i18n } = useTranslation();
    const [tab, setTab] = useState<Tab>('all');
    const [query, setQuery] = useState('');
    const search = useDeferredValue(query.trim().toLowerCase());
    const number = new Intl.NumberFormat(i18n.language);

    const tools = integrations.filter((i) => i.catalogue);
    const platform = integrations.filter((i) => !i.catalogue);
    const count = (status: CatalogueStatus) =>
        tools.filter((i) => i.catalogue?.status === status).length;

    const rows = useMemo(() => {
        const base =
            tab === 'platform'
                ? platform
                : tab === 'all'
                  ? tools
                  : tools.filter((i) => i.catalogue?.status === tab);

        return base.filter(
            (i) =>
                search === '' ||
                i.name.toLowerCase().includes(search) ||
                i.purpose.toLowerCase().includes(search) ||
                t(`integrations.categories.${i.category}`)
                    .toLowerCase()
                    .includes(search),
        );
    }, [tab, tools, platform, search, t]);

    const tabs: { value: Tab; label: string }[] = [
        { value: 'all', label: `${t('catalogue.tabs.all')} ${tools.length}` },
        ...STATUS_TABS.map((status) => ({
            value: status,
            label: `${t(`catalogue.statuses.${status}`)} ${count(status) || ''}`.trim(),
        })),
        {
            value: 'platform' as const,
            label: `${t('catalogue.tabs.platform')} ${platform.length}`,
        },
    ];

    const list = (
        values: string[] | null,
        all: string,
        format: (v: string) => string,
    ) => (values === null ? all : values.map(format).join(', '));

    return (
        <>
            <Head title={t('catalogue.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('catalogue.title')}
                    description={t('catalogue.description')}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={demand()}>
                                {t('catalogue.demandAction')}
                            </Link>
                        </Button>
                    }
                />

                <div
                    role="tablist"
                    aria-label={t('catalogue.tabs.label')}
                    className="flex gap-2 overflow-x-auto border-b border-border"
                >
                    {tabs.map(({ value, label }) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            onClick={() => setTab(value)}
                            className={cn(
                                '-mb-px flex min-h-12 items-center border-b-2 px-3 text-base whitespace-nowrap focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                tab === value
                                    ? 'border-primary font-semibold text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <Input
                    type="search"
                    aria-label={t('integrations.searchLabel')}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={t('integrations.searchPlaceholder')}
                    autoComplete="off"
                    className="max-w-md"
                />

                {rows.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('integrations.noResults.title')}
                        description={t('integrations.noResults.description')}
                        action={
                            <Button
                                onClick={() => {
                                    setQuery('');
                                    setTab('all');
                                }}
                            >
                                {t('integrations.noResults.action')}
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                        <table className="w-full min-w-[60rem] text-left text-sm">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'integration',
                                            'category',
                                            'status',
                                            'states',
                                            'roles',
                                            'plans',
                                            'connected',
                                            'health',
                                            'demand',
                                        ] as const
                                    ).map((column) => (
                                        <th
                                            key={column}
                                            scope="col"
                                            className="px-4 py-3"
                                        >
                                            {t(`catalogue.columns.${column}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rows.map((integration) => {
                                    const c = integration.catalogue;

                                    return (
                                        <tr
                                            key={integration.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <th
                                                scope="row"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                <Link
                                                    href={show(integration.id)}
                                                    className="flex min-h-10 items-center gap-3 text-foreground hover:underline"
                                                >
                                                    <span
                                                        aria-hidden
                                                        className="flex size-8 shrink-0 items-center justify-center rounded-reos-pill bg-accent text-xs font-bold text-primary"
                                                    >
                                                        {integration.name
                                                            .slice(0, 2)
                                                            .toUpperCase()}
                                                    </span>
                                                    {integration.name}
                                                </Link>
                                            </th>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {t(
                                                    `integrations.categories.${integration.category}`,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {c ? (
                                                    <CatalogueStatusBadge
                                                        status={c.status}
                                                    />
                                                ) : (
                                                    t('catalogue.none')
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {c
                                                    ? list(
                                                          c.states,
                                                          t('catalogue.all'),
                                                          (v) => v,
                                                      )
                                                    : t('catalogue.none')}
                                            </td>
                                            <td className="px-4 py-3">
                                                {c
                                                    ? list(
                                                          c.roles,
                                                          t('catalogue.all'),
                                                          (v) =>
                                                              roleName(
                                                                  t,
                                                                  v as Membership['role'],
                                                              ),
                                                      )
                                                    : t('catalogue.none')}
                                            </td>
                                            <td className="px-4 py-3">
                                                {c
                                                    ? list(
                                                          c.plans,
                                                          t(
                                                              'catalogue.allPlans',
                                                          ),
                                                          (v) => v,
                                                      )
                                                    : t('catalogue.none')}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {c && c.connected > 0
                                                    ? number.format(c.connected)
                                                    : t('catalogue.none')}
                                            </td>
                                            <td className="px-4 py-3">
                                                <ModeBadge
                                                    mode={integration.mode}
                                                    paused={
                                                        integration.paused_at !==
                                                        null
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {c && c.demand > 0
                                                    ? t('catalogue.taps', {
                                                          count: c.demand,
                                                      })
                                                    : t('catalogue.none')}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <p className="m-4 flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                            <Info
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0"
                            />
                            {t('catalogue.definitions')}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
