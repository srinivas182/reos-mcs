import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { QuickBooksPanel } from '@/components/accounting/quickbooks';
import type { QuickBooksBoard } from '@/components/accounting/types';
import { PageHeader } from '@/components/page-header';
import {
    accounts,
    connect,
    disconnect,
    exportMethod,
} from '@/routes/accounting';

const routes = {
    connect: connect().url,
    accounts: accounts().url,
    export: exportMethod().url,
    disconnect: disconnect().url,
};

/** QuickBooks Online (B17.3, broker web journey: integrations): split sheets and payouts as journal entries. */
export default function Accounting({ board }: { board: QuickBooksBoard }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('accounting.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('accounting.title')}
                    description={t('accounting.purpose')}
                />
                <QuickBooksPanel
                    board={board}
                    routes={routes}
                    onChanged={() => router.reload({ only: ['board'] })}
                />
            </div>
        </>
    );
}
