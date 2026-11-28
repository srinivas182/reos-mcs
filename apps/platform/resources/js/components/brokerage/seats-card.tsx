import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { putJson } from '@/lib/content';
import type { Payer, SeatsPlan } from './types';

const PAYERS: Payer[] = ['organisation', 'member', 'split'];

/**
 * Seats and plan (A15.1b, broker journey screen 4): who pays for agents'
 * seats and whether AI credits are pooled. Seats are bought on reos.com;
 * nothing is sold here.
 */
export function SeatsCard({
    plan: initial,
    route,
    moreHref,
}: {
    plan: SeatsPlan;
    route: string;
    moreHref: string;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [plan, setPlan] = useState(initial);
    const [payer, setPayer] = useState<Payer>(initial.payer);
    const [pooled, setPooled] = useState(initial.credits_pooled);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const n = new Intl.NumberFormat(i18n.language);

    return (
        <section
            aria-labelledby={`${ids}-title`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="space-y-1">
                <h2 id={`${ids}-title`} className="font-semibold">
                    {t('brokerage.seats.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('brokerage.seats.purpose')}
                </p>
                <p className="text-base">
                    {plan.seats === null
                        ? t('brokerage.seats.usageNoLimit', {
                              used: plan.used,
                              pending: plan.pending,
                          })
                        : t('brokerage.seats.usage', {
                              used: plan.used,
                              seats: plan.seats,
                              pending: plan.pending,
                          })}{' '}
                    · {t('brokerage.seats.teams', { count: plan.teams })}
                </p>
            </header>
            <fieldset className="space-y-2">
                <legend className="font-medium">
                    {t('brokerage.seats.payer')}
                </legend>
                {PAYERS.map((p) => (
                    <label
                        key={p}
                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                    >
                        <input
                            type="radio"
                            name={`${ids}-payer`}
                            className="size-5 accent-primary"
                            checked={payer === p}
                            onChange={() => setPayer(p)}
                        />
                        {t(`brokerage.seats.payers.${p}`)}
                    </label>
                ))}
            </fieldset>
            <label className="flex min-h-12 items-start gap-3">
                <input
                    type="checkbox"
                    className="mt-1 size-5 accent-primary"
                    checked={pooled}
                    aria-describedby={`${ids}-pool`}
                    onChange={(e) => setPooled(e.target.checked)}
                />
                <span>
                    <span className="block text-base">
                        {t('brokerage.seats.pooled')}
                    </span>
                    <span
                        id={`${ids}-pool`}
                        className="block text-sm text-muted-foreground"
                    >
                        {t('brokerage.seats.pooledHint', {
                            credits: n.format(plan.credits_per_seat),
                        })}
                    </span>
                </span>
            </label>
            {plan.pool !== null ? (
                <p className="text-base">
                    {t('brokerage.seats.pool', {
                        credits: n.format(plan.pool),
                    })}
                </p>
            ) : null}
            <p className="text-sm text-muted-foreground">
                {t('brokerage.seats.candidates', {
                    credits: n.format(plan.candidate_pool),
                })}
            </p>
            <InputError message={error ?? undefined} />
            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={busy}
                    onClick={() => {
                        setBusy(true);
                        setError(null);
                        void putJson<{ data: SeatsPlan }>(route, {
                            payer,
                            credits_pooled: pooled,
                        })
                            .then((r) => {
                                setPlan(r.data);
                                toast.success(t('brokerage.seats.saved'));
                            })
                            .catch((e: unknown) =>
                                setError(
                                    e instanceof Error ? e.message : String(e),
                                ),
                            )
                            .finally(() => setBusy(false));
                    }}
                >
                    {t('brokerage.seats.save')}
                </Button>
                <Button variant="outline" asChild>
                    <a href={moreHref}>{t('brokerage.seats.more')}</a>
                </Button>
            </div>
        </section>
    );
}
