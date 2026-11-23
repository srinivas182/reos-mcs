import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plug, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { ManageConnectionDialog } from '@/components/manage-connection-dialog';
import { PageHeader } from '@/components/page-header';
import { RequestToolDialog } from '@/components/request-tool-dialog';
import { ToolCard } from '@/components/tool-card';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';
import { byNextStep, WEB_TABS, webTab } from '@/lib/connection-tabs';
import type { WebTab } from '@/lib/connection-tabs';
import { cn } from '@/lib/utils';
import { forget, interest, transactions } from '@/routes/connections';
import type { CatalogueItem, ToolConnection } from '@/types';

type Props = { tools: CatalogueItem[]; connections: ToolConnection[] };

/**
 * Connections (web journey screen 19): the tools this agent may use, as the
 * super admin's catalogue allows for their state, role and plan, and as
 * their brokerage allows. Tabs by tool type; each card shows its status and
 * the next step: Connect, Manage, Join beta, Notify me or Ask my broker.
 */
export default function Connections({ tools, connections }: Props) {
    const { t } = useTranslation();
    const { region, auth } = usePage().props;
    // Deals from transaction tools (Connections screen 17, B9.1b): agents and brokers.
    const role = auth?.roles?.active?.role;
    const dealsRole = role === 'agent' || role === 'broker';
    const [tab, setTab] = useState<WebTab | 'all'>('all');
    const [managing, setManaging] = useState<ToolConnection | null>(null);
    const [requesting, setRequesting] = useState(false);

    // Only the tool types this person has tools in, in the journey's order.
    const present = WEB_TABS.filter((value) =>
        tools.some((tool) => webTab(tool.category) === value),
    );
    const shown = (
        tab === 'all'
            ? tools
            : tools.filter((tool) => webTab(tool.category) === tab)
    ).toSorted(byNextStep);
    const connectionFor = (tool: CatalogueItem) =>
        connections.find((c) => c.integration === tool.id);

    const follow = (tool: CatalogueItem) =>
        router.post(
            interest(tool.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    undoToast({
                        message:
                            tool.action === 'join_beta'
                                ? t('connections.library.betaToast', {
                                      tool: tool.name,
                                  })
                                : tool.action === 'ask_broker'
                                  ? t('connections.askBroker.toast', {
                                        tool: tool.name,
                                    })
                                  : t('connections.library.notifyToast', {
                                        tool: tool.name,
                                    }),
                        onUndo: () =>
                            new Promise<void>((resolve) =>
                                router.delete(forget(tool.id).url, {
                                    preserveScroll: true,
                                    onFinish: () => resolve(),
                                }),
                            ),
                        undoneMessage: t('connections.library.undone'),
                    }),
            },
        );

    const tabs = [
        { value: 'all' as const, label: t('connections.web.all') },
        ...present.map((value) => ({
            value,
            label:
                value === 'more'
                    ? t('connections.hub.groups.more')
                    : t(`connections.web.tabs.${value}`),
        })),
    ];

    return (
        <>
            <Head title={t('connections.hub.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('connections.hub.title')}
                    description={t('connections.web.purpose', {
                        state: region?.name ?? '',
                    })}
                    actions={
                        <>
                            {dealsRole ? (
                                <Button variant="outline" asChild>
                                    <Link href={transactions()}>
                                        {t('connections.transactions.title')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                variant="outline"
                                onClick={() => setRequesting(true)}
                            >
                                {t('connections.web.requestTool')}
                            </Button>
                        </>
                    }
                />

                {tools.length === 0 ? (
                    <EmptyState
                        icon={Plug}
                        title={t('connections.web.emptyTitle')}
                        description={t('connections.web.emptyBody')}
                        action={
                            <Button onClick={() => setRequesting(true)}>
                                {t('connections.web.requestTool')}
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div
                            role="tablist"
                            aria-label={t('connections.web.tabsLabel')}
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

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 min-[100rem]:grid-cols-4">
                            {shown.map((tool) => (
                                <ToolCard
                                    key={tool.id}
                                    tool={tool}
                                    connection={connectionFor(tool)}
                                    onManage={setManaging}
                                    onFollow={follow}
                                />
                            ))}
                        </div>
                    </>
                )}

                <p className="flex gap-3 rounded-reos-md bg-reos-tone-info-bg p-4 text-body text-reos-tone-info-fg">
                    <ShieldCheck
                        aria-hidden
                        className="mt-0.5 size-5 shrink-0"
                    />
                    {t('connections.web.note')}
                </p>
            </div>

            <ManageConnectionDialog
                key={managing?.id ?? 'none'}
                connection={
                    managing === null
                        ? null
                        : (connections.find((c) => c.id === managing.id) ??
                          null)
                }
                onClose={() => setManaging(null)}
            />
            <RequestToolDialog open={requesting} onOpenChange={setRequesting} />
        </>
    );
}
