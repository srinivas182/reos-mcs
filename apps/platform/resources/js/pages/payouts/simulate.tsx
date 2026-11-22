import { Head, router } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { finish } from '@/routes/payouts/simulate';

/**
 * The Simulator's stand-in for Stripe's payout onboarding (never in
 * production). Plainly a REOS demo page, not a copy of Stripe's: "Finish"
 * makes the account ready for payouts and returns to REOS.
 */
export default function PayoutSimulator({
    account,
    return: back,
}: {
    account: string;
    return: string;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('payouts.simulator.title')} />
            <main className="flex min-h-dvh items-center justify-center bg-background px-4 py-10">
                <div className="w-full max-w-md space-y-6 rounded-reos-lg border border-border bg-card p-8">
                    <div className="flex items-center gap-3">
                        <BrandMark className="size-10" />
                        <span className="inline-flex items-center gap-1.5 rounded-reos-pill bg-reos-tone-warning-bg px-3 py-1 text-sm font-semibold text-reos-tone-warning-fg">
                            <FlaskConical aria-hidden className="size-4" />
                            {t('payouts.simulator.eyebrow')}
                        </span>
                    </div>
                    <header className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('payouts.simulator.title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('payouts.simulator.body')}
                        </p>
                        <p className="font-mono text-sm text-muted-foreground">
                            {account}
                        </p>
                    </header>
                    <div className="flex flex-col gap-2">
                        <Button
                            onClick={() =>
                                router.post(finish(account).url, {
                                    return: back,
                                })
                            }
                        >
                            {t('payouts.simulator.finish')}
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={back}>{t('payouts.simulator.later')}</a>
                        </Button>
                    </div>
                </div>
            </main>
        </>
    );
}
