import { Link } from '@inertiajs/react';
import { BookOpen, FileUp, GraduationCap, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CeRequirementList } from '@/components/ce-requirement';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { priceLabel } from '@/lib/billing';
import { cn } from '@/lib/utils';
import type { CompletionStatus, Wallet } from './types';

const TONE: Record<CompletionStatus, string> = {
    reos_reported: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    self_reported: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    on_record: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

const day = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(
        new Date(`${iso.slice(0, 10)}T12:00:00`),
    );

/**
 * One licence in the CE wallet (A11.2; agent screens 103 and 105): hours
 * against the renewal with each required course and the electives, TREC's
 * own view, every completion in the period and who reported it, and where
 * to get the hours still missing.
 */
export function WalletCard({
    wallet,
    learnUrl,
    buyUrl,
    onAddCertificate,
}: {
    wallet: Wallet;
    learnUrl: string;
    /** Checkout for the plan that unlocks CE, when the agent has none. */
    buyUrl: string | null;
    onAddCertificate: () => void;
}) {
    const { t, i18n } = useTranslation();
    const { licence, ce, trec } = wallet;
    const trecLine = ce.first_renewal
        ? trec.education
            ? t(`ce.wallet.trec.education.${trec.education}`)
            : null
        : trec.mce
          ? t(`ce.wallet.trec.mce.${trec.mce}`)
          : null;

    return (
        <section
            aria-labelledby={`wallet-${licence.id}`}
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2
                    id={`wallet-${licence.id}`}
                    className="text-lg font-semibold"
                >
                    {t('ce.wallet.licence', {
                        state: licence.state_name,
                        number: licence.number,
                    })}
                </h2>
                {ce.due_on ? (
                    <p className="text-base text-muted-foreground">
                        {t('ce.wallet.renewsOn', {
                            date: day(ce.due_on, i18n.language),
                        })}
                        {ce.days_left !== null && ce.days_left >= 0
                            ? ` · ${t('ce.wallet.daysLeft', { count: ce.days_left })}`
                            : null}
                    </p>
                ) : null}
            </div>
            <CeRequirementList ce={ce} />
            <div className="flex items-start gap-3 rounded-reos-md bg-muted p-4">
                <ShieldCheck aria-hidden className="mt-0.5 size-5 shrink-0" />
                <div className="space-y-1 text-base">
                    <p className="font-semibold">{t('ce.wallet.trec.title')}</p>
                    <p>{trecLine ?? t('ce.wallet.trec.unknown')}</p>
                    {trec.checked_at ? (
                        <p className="text-muted-foreground">
                            {t('ce.wallet.trec.checked', {
                                date: day(trec.checked_at, i18n.language),
                            })}
                        </p>
                    ) : null}
                </div>
            </div>
            <div className="flex flex-wrap gap-3">
                {wallet.unlocked ? (
                    <Button asChild>
                        <Link href={learnUrl}>
                            <GraduationCap aria-hidden />
                            {t('ce.wallet.takeCe')}
                        </Link>
                    </Button>
                ) : wallet.buy && buyUrl ? (
                    <Button asChild>
                        <Link href={buyUrl}>
                            <BookOpen aria-hidden />
                            {t('ce.wallet.buyFrom', {
                                price: priceLabel(
                                    t,
                                    wallet.buy.interval,
                                    wallet.buy.amount,
                                    i18n.language,
                                ),
                            })}
                        </Link>
                    </Button>
                ) : null}
                <Button variant="outline" onClick={onAddCertificate}>
                    <FileUp aria-hidden />
                    {t('ce.wallet.addCertificate')}
                </Button>
            </div>
            <div className="space-y-3">
                <h3 className="text-base font-semibold">
                    {t('ce.wallet.completions')}
                </h3>
                {wallet.completions.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title={t('ce.wallet.emptyTitle')}
                        description={t('ce.wallet.emptyBody')}
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                        {wallet.completions.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="text-base font-semibold">
                                        {c.title}
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        {[
                                            day(c.completed_on, i18n.language),
                                            c.provider,
                                            t('ce.wallet.hoursShort', {
                                                hours: c.hours,
                                            }),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                    {c.status === 'self_reported' ? (
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'ce.wallet.statusHint.self_reported',
                                            )}
                                        </p>
                                    ) : null}
                                </div>
                                <span
                                    className={cn(
                                        'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                        TONE[c.status],
                                    )}
                                >
                                    {t(`ce.wallet.status.${c.status}`)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
