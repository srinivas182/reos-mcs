import { Head, router, useForm } from '@inertiajs/react';
import { Building2, RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CatalogueStatusBadge } from '@/components/catalogue-status-badge';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { timeAgo } from '@/lib/time-ago';
import { nativeSelect } from '@/lib/tenants';
import { allow, mapping as saveMapping } from '@/routes/brokerage-integrations';
import { connect, destroy, interest, sync } from '@/routes/connections';
import type { CatalogueStatus } from '@/types';

type Mapping = {
    contact_stage: string | null;
    lead_stage: string;
    leads: boolean;
};

type Tool = {
    id: string;
    name: string;
    category: string;
    status: CatalogueStatus;
    accounts: 'user' | 'tenant';
    action: string;
    allowed: boolean;
    may_hide: boolean;
    agents_connected: number;
    account: {
        connection: number;
        label: string | null;
        status: string;
        last_synced_at: string | null;
        people: number;
        agents: number;
        unmatched_owners: string[];
        mapping: Mapping | null;
    } | null;
};

type Fields = {
    Contact: { name: string; label: string }[];
    Lead: { name: string; label: string }[];
};

/**
 * Brokerage integrations (broker web screen 40, B8.2): the brokerage's own
 * accounts (connect once for everyone; for a CRM its sync health, counts
 * only, and where it keeps a stage), then every tool REOS offers agents with
 * REOS's status, whether agents may use it, and how many have connected.
 * Brokerages can only hide tools, never turn on one REOS hasn't released.
 */
export default function BrokerageIntegrationsPage({
    brokerage,
    tools,
    stage_fields: stageFields,
}: {
    brokerage: { id: number; name: string };
    tools: Tool[];
    stage_fields: Record<string, Fields | null>;
}) {
    const { t } = useTranslation();
    const own = tools.filter((tool) => tool.accounts === 'tenant');
    const agents = tools.filter((tool) => tool.accounts === 'user');

    return (
        <>
            <Head title={t('brokerageIntegrations.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerageIntegrations.title')}
                    description={t('brokerageIntegrations.description', {
                        brokerage: brokerage.name,
                    })}
                />

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('brokerageIntegrations.ownTitle')}
                    </h2>
                    <p className="text-body text-muted-foreground">
                        {t('brokerageIntegrations.ownIntro')}
                    </p>
                    <ul className="space-y-4">
                        {own.map((tool) => (
                            <li key={tool.id}>
                                <OwnAccount
                                    tool={tool}
                                    fields={stageFields[tool.id] ?? null}
                                />
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('brokerageIntegrations.agentsTitle')}
                    </h2>
                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                        <table className="w-full text-left text-body">
                            <thead className="border-b border-border text-sm text-muted-foreground">
                                <tr>
                                    <th className="p-3 font-medium">
                                        {t(
                                            'brokerageIntegrations.columns.tool',
                                        )}
                                    </th>
                                    <th className="p-3 font-medium">
                                        {t(
                                            'brokerageIntegrations.columns.status',
                                        )}
                                    </th>
                                    <th className="p-3 font-medium">
                                        {t(
                                            'brokerageIntegrations.columns.allowed',
                                        )}
                                    </th>
                                    <th className="p-3 font-medium">
                                        {t(
                                            'brokerageIntegrations.columns.agents',
                                        )}
                                    </th>
                                    <th className="p-3">
                                        <span className="sr-only">
                                            {t(
                                                'brokerageIntegrations.columns.actions',
                                            )}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {agents.map((tool) => (
                                    <tr key={tool.id}>
                                        <th
                                            scope="row"
                                            className="p-3 font-medium"
                                        >
                                            {tool.name}
                                        </th>
                                        <td className="p-3">
                                            <CatalogueStatusBadge
                                                status={tool.status}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <Switch
                                                checked={tool.allowed}
                                                disabled={
                                                    tool.allowed &&
                                                    !tool.may_hide
                                                }
                                                aria-label={t(
                                                    'brokerageIntegrations.allow',
                                                    { tool: tool.name },
                                                )}
                                                onCheckedChange={(on) =>
                                                    router.put(
                                                        allow(tool.id).url,
                                                        { allowed: on },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="p-3 tabular-nums">
                                            {tool.agents_connected}
                                        </td>
                                        <td className="p-3 text-right">
                                            {tool.action === 'notify' ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            interest(tool.id)
                                                                .url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {t(
                                                        'brokerageIntegrations.notify',
                                                    )}
                                                </Button>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

/** A tool the brokerage connects once: its account, sync health and mapping. */
function OwnAccount({ tool, fields }: { tool: Tool; fields: Fields | null }) {
    const { t, i18n } = useTranslation();
    const account = tool.account;

    return (
        <article className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="flex items-center gap-2 font-semibold">
                    <Building2 aria-hidden className="size-5 text-primary" />
                    {tool.name}
                    <CatalogueStatusBadge status={tool.status} />
                </h3>
                {account ? (
                    <div className="flex flex-wrap gap-3">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    sync(account.connection).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw aria-hidden />
                            {t('brokerageIntegrations.syncNow')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.delete(destroy(account.connection).url, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {t('brokerageIntegrations.disconnect')}
                        </Button>
                    </div>
                ) : tool.action === 'connect' ? (
                    <Button asChild>
                        <a href={connect(tool.id).url}>
                            {t('brokerageIntegrations.connect', {
                                tool: tool.name,
                            })}
                        </a>
                    </Button>
                ) : tool.action === 'notify' ? (
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.post(
                                interest(tool.id).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {t('brokerageIntegrations.notify')}
                    </Button>
                ) : null}
            </div>

            {account ? (
                <>
                    <dl className="grid gap-3 text-body sm:grid-cols-4">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('brokerageIntegrations.account')}
                            </dt>
                            <dd className="font-medium">{account.label}</dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('brokerageIntegrations.lastSync')}
                            </dt>
                            <dd className="font-medium">
                                {account.last_synced_at
                                    ? timeAgo(
                                          account.last_synced_at,
                                          i18n.language,
                                      )
                                    : t('brokerageIntegrations.notYet')}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('brokerageIntegrations.people')}
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {account.people}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('brokerageIntegrations.agentsWith')}
                            </dt>
                            <dd className="font-medium tabular-nums">
                                {account.agents}
                            </dd>
                        </div>
                    </dl>
                    <p className="text-sm text-muted-foreground">
                        {t('brokerageIntegrations.privacy')}
                    </p>
                    {account.unmatched_owners.length > 0 ? (
                        <p className="rounded-reos-md bg-reos-tone-warning-bg p-3 text-body text-reos-tone-warning-fg">
                            {t('brokerageIntegrations.unmatched', {
                                count: account.unmatched_owners.length,
                                owners: account.unmatched_owners.join(', '),
                            })}
                        </p>
                    ) : null}
                    {account.mapping ? (
                        <MappingForm
                            connection={account.connection}
                            mapping={account.mapping}
                            fields={fields}
                        />
                    ) : null}
                </>
            ) : null}
        </article>
    );
}

/** Where the brokerage keeps a person's stage (Salesforce): checked against the org's fields. */
function MappingForm({
    connection,
    mapping,
    fields,
}: {
    connection: number;
    mapping: Mapping;
    fields: Fields | null;
}) {
    const { t } = useTranslation();
    const form = useForm({
        contact_stage: mapping.contact_stage ?? '',
        lead_stage: mapping.lead_stage,
        leads: mapping.leads,
    });
    const contactOptions =
        fields?.Contact ??
        (mapping.contact_stage
            ? [{ name: mapping.contact_stage, label: mapping.contact_stage }]
            : []);
    const leadOptions = fields?.Lead ?? [
        { name: mapping.lead_stage, label: mapping.lead_stage },
    ];
    const common = contactOptions.find((f) => f.name === 'Stage__c');

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(saveMapping(connection).url, { preserveScroll: true });
            }}
            className="space-y-4 rounded-reos-md border border-border p-4"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h4 className="font-semibold">
                    {t('brokerageIntegrations.mapping.title')}
                </h4>
                {common && form.data.contact_stage !== common.name ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData({
                                ...form.data,
                                contact_stage: common.name,
                                lead_stage: 'Status',
                            })
                        }
                    >
                        {t('brokerageIntegrations.mapping.common')}
                    </Button>
                ) : null}
            </div>
            <p className="text-body text-muted-foreground">
                {t('brokerageIntegrations.mapping.intro')}
            </p>
            {fields === null ? (
                <p className="text-sm text-muted-foreground">
                    {t('brokerageIntegrations.mapping.offline')}
                </p>
            ) : null}
            <div className="grid gap-4 md:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor="contact_stage">
                        {t('brokerageIntegrations.mapping.contact')}
                    </Label>
                    <select
                        id="contact_stage"
                        className={nativeSelect}
                        value={form.data.contact_stage}
                        onChange={(e) =>
                            form.setData('contact_stage', e.target.value)
                        }
                    >
                        <option value="">
                            {t('brokerageIntegrations.mapping.none')}
                        </option>
                        {contactOptions.map((f) => (
                            <option
                                key={f.name}
                                value={f.name}
                            >{`${f.label} (${f.name})`}</option>
                        ))}
                    </select>
                    {form.errors.contact_stage ? (
                        <p className="text-sm text-destructive">
                            {form.errors.contact_stage}
                        </p>
                    ) : null}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="lead_stage">
                        {t('brokerageIntegrations.mapping.lead')}
                    </Label>
                    <select
                        id="lead_stage"
                        className={nativeSelect}
                        value={form.data.lead_stage}
                        onChange={(e) =>
                            form.setData('lead_stage', e.target.value)
                        }
                    >
                        {leadOptions.map((f) => (
                            <option
                                key={f.name}
                                value={f.name}
                            >{`${f.label} (${f.name})`}</option>
                        ))}
                    </select>
                </div>
                <div className="flex items-center gap-3 self-end pb-3">
                    <Switch
                        id="leads"
                        checked={form.data.leads}
                        onCheckedChange={(on) => form.setData('leads', on)}
                    />
                    <Label htmlFor="leads">
                        {t('brokerageIntegrations.mapping.leads')}
                    </Label>
                </div>
            </div>
            <Button type="submit" disabled={form.processing}>
                {t('brokerageIntegrations.mapping.save')}
            </Button>
        </form>
    );
}
