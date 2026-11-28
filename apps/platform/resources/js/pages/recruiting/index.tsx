import { Head, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { OfferDialog } from '@/components/recruiting/offer-dialog';
import {
    OpenAgents,
    SponsoredHelp,
} from '@/components/recruiting/recruit-more';
import type {
    OpenAgent,
    RecruitCandidate,
    SponsoredBoard,
} from '@/components/recruiting/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';
import { cap, invite, withdraw } from '@/routes/recruiting';

/**
 * Recruiting (A10.3b, broker web sidebar): candidates in the brokerage's
 * states who asked for offers, as a first name, city and stage; make,
 * change or withdraw an offer; contact details once they accept.
 */
export default function RecruitingPage({
    brokerage,
    candidates,
    sponsored = { pool: 0, candidates: [] },
    agents = [],
}: {
    brokerage: { name: string; states: string[] };
    candidates: RecruitCandidate[];
    /** Sponsored AI help (A17.2, screen 26). */
    sponsored?: SponsoredBoard;
    /** Licensed agents open to a move (A17.2, screen 27). */
    agents?: OpenAgent[];
}) {
    const { t, i18n } = useTranslation();
    const [editing, setEditing] = useState<RecruitCandidate | null>(null);

    return (
        <>
            <Head title={t('recruiting.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('recruiting.title')}
                    description={t('recruiting.purpose', {
                        states: brokerage.states.join(', '),
                    })}
                />
                {candidates.length === 0 ? (
                    <EmptyState
                        icon={UserPlus}
                        title={t('recruiting.empty')}
                        description={t('recruiting.emptyBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {candidates.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-reos-lg border border-border bg-card p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <h2 className="text-base font-semibold">
                                        {[c.first_name, c.city]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t(`recruiting.stages.${c.stage}`)}
                                        {c.offer
                                            ? ` · ${t(`licence.offers.statuses.${c.offer.status}`)}: ${t('licence.offers.split', { percent: c.offer.split_percent })}, ${
                                                  c.offer.monthly_fee_cents ===
                                                  0
                                                      ? t(
                                                            'licence.offers.noFee',
                                                        )
                                                      : t(
                                                            'licence.offers.fee',
                                                            {
                                                                fee: money(
                                                                    c.offer
                                                                        .monthly_fee_cents,
                                                                    i18n.language,
                                                                ),
                                                            },
                                                        )
                                              }`
                                            : ''}
                                    </p>
                                    {c.offer?.contact ? (
                                        <p className="text-base font-medium">
                                            {c.offer.contact.name} ·{' '}
                                            {t('recruiting.contact', {
                                                email: c.offer.contact.email,
                                                phone:
                                                    c.offer.contact.phone ??
                                                    '—',
                                            })}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {c.offer === null ||
                                    c.offer.status === 'open' ||
                                    c.offer.status === 'withdrawn' ? (
                                        <Button
                                            variant={
                                                c.offer?.status === 'open'
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            onClick={() => setEditing(c)}
                                        >
                                            {c.offer?.status === 'open'
                                                ? t('recruiting.changeOffer')
                                                : t('recruiting.makeOffer')}
                                        </Button>
                                    ) : null}
                                    {c.offer?.status === 'open' ? (
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                c.offer &&
                                                router.post(
                                                    withdraw(c.offer.id).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('recruiting.withdraw')}
                                        </Button>
                                    ) : null}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <SponsoredHelp
                    board={sponsored}
                    onCap={(id, value) =>
                        router.put(
                            cap(id).url,
                            { cap: value },
                            { preserveScroll: true },
                        )
                    }
                />
                <OpenAgents
                    agents={agents}
                    onInvite={(a) =>
                        router.post(invite(a.id).url, { message: '' })
                    }
                />
            </div>
            <OfferDialog
                key={editing?.id ?? 'none'}
                candidate={editing}
                onClose={() => setEditing(null)}
            />
        </>
    );
}
