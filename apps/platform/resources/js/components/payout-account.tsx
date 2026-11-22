import { router } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PayoutAccount } from '@/lib/payouts';
import { cn } from '@/lib/utils';

const TONE = {
    ready: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    onboarding: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    needsInfo: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
} as const;

/**
 * Where payouts go (B5.2b): the Stripe account's state and the one action
 * that moves it on (set up, finish, or update details on Stripe's own
 * pages; bank and tax details never touch REOS).
 */
export function PayoutAccountCard({
    account,
    connectUrl,
    ownUrl,
}: {
    account: PayoutAccount;
    /** Posts to start or continue Stripe's onboarding. */
    connectUrl: string;
    /** "Connect your own Stripe" (schools, when allowed). */
    ownUrl?: string | null;
}) {
    const { t } = useTranslation();
    const state = account?.state ?? null;

    return (
        <section
            aria-labelledby="payout-account"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex items-start justify-between gap-3">
                <h2
                    id="payout-account"
                    className="flex items-center gap-2 font-semibold"
                >
                    <Landmark aria-hidden className="size-5 text-primary" />
                    {t('payouts.account.title')}
                </h2>
                {state ? (
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-0.5 text-sm font-medium whitespace-nowrap',
                            TONE[state],
                        )}
                    >
                        {t(`payouts.account.states.${state}`)}
                    </span>
                ) : null}
            </div>
            <p className="text-body text-muted-foreground">
                {state === null
                    ? t('payouts.account.none')
                    : state === 'ready'
                      ? account?.kind === 'standard'
                          ? t('payouts.account.readyOwn')
                          : t('payouts.account.ready')
                      : t('payouts.account.notReady', {
                            count: account?.requirements ?? 0,
                        })}
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant={state === 'ready' ? 'outline' : 'default'}
                    onClick={() => router.post(connectUrl)}
                >
                    {state === null
                        ? t('payouts.account.setUp')
                        : state === 'ready'
                          ? t('payouts.account.update')
                          : t('payouts.account.finish')}
                </Button>
                {ownUrl && account?.kind !== 'standard' ? (
                    <Button
                        variant="outline"
                        onClick={() => router.post(ownUrl)}
                    >
                        {t('payouts.account.connectOwn')}
                    </Button>
                ) : null}
            </div>
        </section>
    );
}
