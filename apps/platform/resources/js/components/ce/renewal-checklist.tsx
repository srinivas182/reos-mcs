import {
    CheckCircle2,
    Circle,
    ExternalLink,
    PartyPopper,
    RotateCcw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Wallet } from './types';

const day = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(
        new Date(`${iso.slice(0, 10)}T12:00:00`),
    );

/**
 * Renew your licence (A11.2; agent screen 106): the checklist from the
 * state's rules (CE reported, sponsoring broker, renew with TREC, confirmed),
 * the expiry date, and "I've renewed"; REOS confirms the new expiry on
 * TREC's record overnight. Renewal fees are paid to TREC.
 */
export function RenewalChecklist({
    wallet,
    onRenewed,
}: {
    wallet: Wallet;
    onRenewed: (renewed: boolean) => void;
}) {
    const { t, i18n } = useTranslation();
    const { renewal } = wallet;
    const renewed = renewal.renewed_at !== null;

    return (
        <section
            aria-labelledby={`renew-${wallet.licence.id}`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2
                    id={`renew-${wallet.licence.id}`}
                    className="text-lg font-semibold"
                >
                    {t('ce.wallet.renew.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {renewal.ready
                        ? t('ce.wallet.renew.ready')
                        : t('ce.wallet.renew.notReady')}
                    {renewal.due_on
                        ? ` ${t('ce.wallet.renew.expires', { date: day(renewal.due_on, i18n.language) })}`
                        : null}
                </p>
            </div>
            {renewal.renewed_until ? (
                <p className="flex items-center gap-2 text-base font-semibold">
                    <PartyPopper aria-hidden className="size-5" />
                    {t('ce.wallet.renew.confirmed', {
                        date: day(renewal.renewed_until, i18n.language),
                    })}
                </p>
            ) : null}
            <ol aria-label={t('ce.wallet.renew.steps')} className="space-y-3">
                {renewal.steps.map((step) => (
                    <li
                        key={step.code}
                        className="flex flex-wrap items-start gap-3"
                    >
                        {step.done ? (
                            <CheckCircle2
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                            />
                        ) : (
                            <Circle
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            />
                        )}
                        <div className="min-w-0 flex-1 space-y-1">
                            <p
                                className={cn(
                                    'text-base font-semibold',
                                    step.done && 'text-muted-foreground',
                                )}
                            >
                                {step.title}
                                <span className="sr-only">
                                    {' '}
                                    (
                                    {step.done
                                        ? t('ce.wallet.renew.done')
                                        : t('ce.wallet.renew.todo')}
                                    )
                                </span>
                            </p>
                            <p className="text-base text-muted-foreground">
                                {step.detail}
                            </p>
                        </div>
                        {step.url && !step.done ? (
                            <Button asChild variant="outline">
                                <a
                                    href={step.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink aria-hidden />
                                    {t('ce.wallet.renew.open')}
                                    <span className="sr-only">
                                        : {step.title}
                                    </span>
                                </a>
                            </Button>
                        ) : null}
                    </li>
                ))}
            </ol>
            <p className="text-base text-muted-foreground">
                {t('ce.wallet.renew.fees')}
            </p>
            {renewal.confirmed_at === null && renewal.due_on !== null ? (
                renewed ? (
                    <Button variant="outline" onClick={() => onRenewed(false)}>
                        <RotateCcw aria-hidden />
                        {t('ce.wallet.renew.undoRenewed')}
                    </Button>
                ) : (
                    <Button onClick={() => onRenewed(true)}>
                        {t('ce.wallet.renew.renewed')}
                    </Button>
                )
            ) : null}
        </section>
    );
}
