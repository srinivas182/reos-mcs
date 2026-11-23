import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, FileCheck2, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { money, useDateText } from '@/components/deals/deal-card';
import { TOOL_NAMES, type DealCard } from '@/components/deals/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as connections } from '@/routes/connections';
import { index as dealsBoard } from '@/routes/deals';

/**
 * Transactions: deal state (Connections and Sync screen 17, B9.1b): each
 * deal from the agent's transaction tools with its stage, documents
 * complete, next deadline and commission. Read only: changes are made in
 * the tool.
 */
export default function TransactionsPage({ deals }: { deals: DealCard[] }) {
    const { t, i18n } = useTranslation();
    const dateText = useDateText();

    return (
        <>
            <Head title={t('connections.transactions.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('connections.transactions.title')}
                    description={t('connections.transactions.description')}
                    back={
                        <Link
                            href={connections()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('connections.hub.title')}
                        </Link>
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={dealsBoard()}>{t('deals.title')}</Link>
                        </Button>
                    }
                />
                {deals.length === 0 ? (
                    <EmptyState
                        icon={FileCheck2}
                        title={t('connections.transactions.emptyTitle')}
                        description={t('connections.transactions.empty')}
                    />
                ) : (
                    <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                        <table className="w-full min-w-[52rem] text-base">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'deal',
                                            'stage',
                                            'documents',
                                            'next',
                                            'commission',
                                            'tool',
                                        ] as const
                                    ).map((c) => (
                                        <th
                                            key={c}
                                            scope="col"
                                            className="px-3 py-2 text-left"
                                        >
                                            {t(
                                                `connections.transactions.columns.${c}`,
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {deals.map((deal) => (
                                    <tr key={deal.uuid}>
                                        <th
                                            scope="row"
                                            className="px-3 py-3 text-left font-semibold"
                                        >
                                            {deal.property}
                                        </th>
                                        <td className="px-3 py-3">
                                            {t(`deals.stages.${deal.stage}`)}
                                        </td>
                                        <td className="px-3 py-3">
                                            {t('deals.documents', {
                                                complete:
                                                    deal.documents.complete,
                                                total: deal.documents.total,
                                            })}
                                        </td>
                                        <td className="px-3 py-3">
                                            {deal.next
                                                ? dateText(deal.next)
                                                : t('deals.noNext')}
                                        </td>
                                        <td className="px-3 py-3">
                                            {deal.commission !== null
                                                ? money(
                                                      deal.commission,
                                                      i18n.language,
                                                  )
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-3">
                                            {deal.external_url ? (
                                                <a
                                                    href={deal.external_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex min-h-touch items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    <ExternalLink
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                    {t('deals.openIn', {
                                                        tool:
                                                            TOOL_NAMES[
                                                                deal.source
                                                            ] ?? deal.source,
                                                    })}
                                                </a>
                                            ) : (
                                                (TOOL_NAMES[deal.source] ??
                                                deal.source)
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
                <p className="flex items-center gap-2 text-body text-muted-foreground">
                    <Lock aria-hidden className="size-4" />
                    {t('connections.transactions.readOnly')}
                </p>
            </div>
        </>
    );
}
