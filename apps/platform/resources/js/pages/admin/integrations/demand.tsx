import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BellRing, Inbox } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { index, show } from '@/routes/integrations';

type Props = {
    tools: { id: string; name: string; notify: number; beta: number }[];
    requests: { id: number; name: string; note: string | null; at: string }[];
};

/**
 * Notify-me demand (web journey screen 64): the tools people tapped "Notify
 * me" or "Join beta" on, most wanted first, and the tools they asked for.
 */
export default function Demand({ tools, requests }: Props) {
    const { t, i18n } = useTranslation();
    const date = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'medium',
    });

    return (
        <>
            <Head title={t('catalogue.demand.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('catalogue.demand.title')}
                    description={t('catalogue.demand.description')}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('catalogue.demand.back')}
                        </Link>
                    }
                />

                {tools.length === 0 ? (
                    <EmptyState
                        icon={BellRing}
                        title={t('catalogue.demand.toolsEmpty')}
                        description={t('catalogue.demand.toolsEmptyBody')}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                        <table className="w-full text-left">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        {t('catalogue.demand.tool')}
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        {t('catalogue.demand.notify')}
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        {t('catalogue.demand.beta')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tools.map((tool) => (
                                    <tr key={tool.id}>
                                        <th
                                            scope="row"
                                            className="px-4 py-3 font-semibold"
                                        >
                                            <Link
                                                href={show(tool.id)}
                                                className="hover:underline"
                                            >
                                                {tool.name}
                                            </Link>
                                        </th>
                                        <td className="px-4 py-3 tabular-nums">
                                            {tool.notify}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {tool.beta}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <section aria-labelledby="requests-title" className="space-y-3">
                    <h2 id="requests-title" className="text-lg font-semibold">
                        {t('catalogue.demand.requests')}
                    </h2>
                    {requests.length === 0 ? (
                        <EmptyState
                            icon={Inbox}
                            title={t('catalogue.demand.requestsEmpty')}
                            description={t(
                                'catalogue.demand.requestsEmptyBody',
                            )}
                        />
                    ) : (
                        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                            {requests.map((request) => (
                                <li
                                    key={request.id}
                                    className="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3"
                                >
                                    <span>
                                        <span className="font-semibold">
                                            {request.name}
                                        </span>
                                        {request.note ? (
                                            <span className="block text-sm text-muted-foreground">
                                                {request.note}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {date.format(new Date(request.at))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
