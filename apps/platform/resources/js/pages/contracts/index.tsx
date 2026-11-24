import { Head, Link, router } from '@inertiajs/react';
import { FileSearch } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChooseContract } from '@/components/contracts/choose-contract';
import type {
    ContractReview,
    ContractsOverview,
} from '@/components/contracts/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { postForm, postJson } from '@/lib/content';
import { show, store } from '@/routes/contracts';

/**
 * Contract review (A12.2; agent web screen 15 with screen 63): choose a
 * contract (a PDF, photos, a deal's document, or the sample TREC 20-18),
 * and the reviews so far.
 */
export default function ContractsPage({
    contracts,
}: {
    contracts: ContractsOverview;
}) {
    const { t, i18n } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const start = (send: () => Promise<{ data: ContractReview }>) => {
        setBusy(true);
        setError(null);
        send()
            .then(({ data }) => router.visit(show(data.id).url))
            .catch((e: unknown) =>
                setError(e instanceof Error ? e.message : String(e)),
            )
            .finally(() => setBusy(false));
    };

    return (
        <>
            <Head title={t('contracts.web.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('contracts.web.title')}
                    description={t('contracts.web.purpose')}
                />
                <ChooseContract
                    overview={contracts}
                    busy={busy}
                    onFile={(file) => {
                        const form = new FormData();
                        form.append('file', file);
                        start(() =>
                            postForm<{ data: ContractReview }>(
                                store().url,
                                form,
                            ),
                        );
                    }}
                    onDealFile={(uuid) =>
                        start(() =>
                            postJson<{ data: ContractReview }>(store().url, {
                                deal_file: uuid,
                            }),
                        )
                    }
                    onSample={() =>
                        start(() =>
                            postJson<{ data: ContractReview }>(store().url, {
                                sample: true,
                            }),
                        )
                    }
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <section aria-labelledby="cr-list" className="space-y-3">
                    <h2 id="cr-list" className="text-lg font-semibold">
                        {t('contracts.web.reviews')}
                    </h2>
                    {contracts.reviews.length === 0 ? (
                        <EmptyState
                            icon={FileSearch}
                            title={t('contracts.web.empty')}
                            description={t('contracts.web.emptyBody')}
                        />
                    ) : (
                        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                            {contracts.reviews.map((r) => {
                                const high = r.findings.filter(
                                    (f) => f.severity === 'high',
                                ).length;
                                return (
                                    <li key={r.id}>
                                        <Link
                                            href={show(r.id).url}
                                            className="flex min-h-12 flex-wrap items-center justify-between gap-3 p-4 hover:bg-accent"
                                        >
                                            <span className="min-w-0 space-y-1">
                                                <span className="block text-base font-semibold">
                                                    {r.form_name ?? r.file_name}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {[
                                                        r.deal?.property,
                                                        r.created_at
                                                            ? new Intl.DateTimeFormat(
                                                                  i18n.language,
                                                                  {
                                                                      dateStyle:
                                                                          'medium',
                                                                  },
                                                              ).format(
                                                                  new Date(
                                                                      r.created_at,
                                                                  ),
                                                              )
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {r.status === 'done' &&
                                                high > 0 ? (
                                                    <Badge variant="destructive">
                                                        {t(
                                                            'contracts.web.highCount',
                                                            { count: high },
                                                        )}
                                                    </Badge>
                                                ) : null}
                                                <Badge
                                                    variant={
                                                        r.status === 'done'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {t(
                                                        `contracts.web.status.${r.status}`,
                                                    )}
                                                </Badge>
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
