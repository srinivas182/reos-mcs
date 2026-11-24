import { Head, router } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CertificateDialog } from '@/components/certificate-dialog';
import { RenewalChecklist } from '@/components/ce/renewal-checklist';
import type { Wallet } from '@/components/ce/types';
import { WalletCard } from '@/components/ce/wallet-card';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import {
    certificate as certificateRoute,
    renewal as renewalRoute,
} from '@/routes/ce';
import { show as checkout } from '@/routes/checkout';
import { index as learn } from '@/routes/learn';

/**
 * My CE wallet (A11.2; agent screens 103, 105 and 106 in the web shell):
 * each licence's hours against its renewal, who reported each completion,
 * TREC's own view, a certificate from another provider, getting CE, and
 * the renewal checklist.
 */
export default function CeWalletPage({
    wallets: initial,
}: {
    wallets: Wallet[];
}) {
    const { t } = useTranslation();
    const [wallets, setWallets] = useState(initial);
    const [adding, setAdding] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const renew = async (wallet: Wallet, renewed: boolean) => {
        try {
            const { data } = await putJson<{ data: Wallet | null }>(
                renewalRoute(wallet.licence.id).url,
                { renewed },
            );
            if (data)
                setWallets((all) =>
                    all.map((w) =>
                        w.licence.id === data.licence.id ? data : w,
                    ),
                );
            setError(null);
            if (renewed)
                undoToast({
                    message: t('ce.wallet.renew.renewedDone'),
                    onUndo: () => renew(wallet, false),
                });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('ce.wallet.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('ce.wallet.title')}
                    description={t('ce.wallet.description')}
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                {wallets.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title={t('ce.wallet.emptyTitle')}
                        description={t('ce.wallet.emptyBody')}
                    />
                ) : null}
                {wallets.map((wallet) => (
                    <div
                        key={wallet.licence.id}
                        className="grid gap-6 xl:grid-cols-[3fr_2fr]"
                    >
                        <WalletCard
                            wallet={wallet}
                            learnUrl={learn().url}
                            buyUrl={
                                wallet.buy
                                    ? checkout(wallet.buy.price).url
                                    : null
                            }
                            certificateUrl={(completion) =>
                                certificateRoute(completion).url
                            }
                            onAddCertificate={() => setAdding(true)}
                        />
                        <RenewalChecklist
                            wallet={wallet}
                            onRenewed={(renewed) => void renew(wallet, renewed)}
                        />
                    </div>
                ))}
                <CertificateDialog
                    open={adding}
                    onOpenChange={(open) => {
                        setAdding(open);
                        if (!open)
                            router.reload({
                                only: ['wallets'],
                                onSuccess: (page) =>
                                    setWallets(page.props.wallets as Wallet[]),
                            });
                    }}
                />
            </div>
        </>
    );
}
