import { Head, Link } from '@inertiajs/react';
import { ChevronRight, KeyRound, SearchX } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { ModeBadge } from '@/components/integration-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { i18n } from '@/lib/i18n';
import { index, show } from '@/routes/integrations';
import type { IntegrationCategory, IntegrationSummary } from '@/types';

type Props = {
    integrations: IntegrationSummary[];
};

/**
 * Settings › Integrations (super admin): every service in the integrations
 * register, grouped by category, with how it runs and whether keys are saved.
 */
export default function IntegrationsIndex({ integrations }: Props) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const search = useDeferredValue(query.trim().toLowerCase());

    const groups = useMemo(() => {
        const matches = integrations.filter(
            (integration) =>
                search === '' ||
                integration.name.toLowerCase().includes(search) ||
                integration.purpose.toLowerCase().includes(search) ||
                t(`integrations.categories.${integration.category}`)
                    .toLowerCase()
                    .includes(search),
        );

        const byCategory = new Map<IntegrationCategory, IntegrationSummary[]>();

        for (const integration of matches) {
            byCategory.set(integration.category, [
                ...(byCategory.get(integration.category) ?? []),
                integration,
            ]);
        }

        return [...byCategory.entries()];
    }, [integrations, search, t]);

    const configured = integrations.filter(
        (integration) =>
            integration.needs_keys &&
            (integration.keys.sandbox || integration.keys.live),
    ).length;
    const needingKeys = integrations.filter((i) => i.needs_keys).length;

    return (
        <>
            <Head title={t('integrations.title')} />

            <h1 className="sr-only">{t('integrations.title')}</h1>

            <div className="space-y-8">
                <div className="space-y-2">
                    <Heading
                        variant="small"
                        title={t('integrations.title')}
                        description={t('integrations.description')}
                    />
                    <p className="text-sm font-medium text-muted-foreground">
                        {t('integrations.summary', {
                            configured,
                            total: needingKeys,
                        })}
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="integration-search">
                        {t('integrations.searchLabel')}
                    </Label>
                    <Input
                        id="integration-search"
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('integrations.searchPlaceholder')}
                        autoComplete="off"
                    />
                </div>

                {groups.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('integrations.noResults.title')}
                        description={t('integrations.noResults.description')}
                        action={
                            <Button onClick={() => setQuery('')}>
                                {t('integrations.noResults.action')}
                            </Button>
                        }
                    />
                ) : (
                    groups.map(([category, items]) => (
                        <section
                            key={category}
                            aria-labelledby={`category-${category}`}
                            className="space-y-3"
                        >
                            <h2
                                id={`category-${category}`}
                                className="text-lg font-semibold text-foreground"
                            >
                                {t(`integrations.categories.${category}`)}
                            </h2>
                            <ul className="space-y-3">
                                {items.map((integration) => (
                                    <li key={integration.id}>
                                        <IntegrationRow
                                            integration={integration}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

function IntegrationRow({ integration }: { integration: IntegrationSummary }) {
    const { t } = useTranslation();
    const hasKeys = integration.keys.sandbox || integration.keys.live;

    return (
        <Link
            href={show(integration.id)}
            className="flex min-h-touch items-center gap-4 rounded-reos-md border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
        >
            <span className="min-w-0 flex-1 space-y-1">
                <span className="block font-semibold text-foreground">
                    {integration.name}
                </span>
                <span className="line-clamp-2 block text-sm text-muted-foreground">
                    {integration.purpose}
                </span>
                {integration.needs_keys ? (
                    <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <KeyRound aria-hidden className="size-4" />
                        {hasKeys
                            ? t('integrations.keys.status.saved')
                            : t('integrations.keys.status.missing')}
                    </span>
                ) : null}
            </span>
            <ModeBadge mode={integration.mode} />
            <ChevronRight
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}

IntegrationsIndex.layout = {
    breadcrumbs: [
        {
            get title() {
                return i18n.t('integrations.title');
            },
            href: index(),
        },
    ],
};
