import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { OversightBoard } from '@/components/brokerage/oversight-board';
import type { OversightBoardData } from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { agent, settings } from '@/routes/oversight';

const routes = {
    agent: (id: number) => agent(id).url,
    settings: settings().url,
};

/** Brokerage › Agents and deals (A16.1): agents needing help and deals at risk. */
export default function Oversight({ board }: { board: OversightBoardData }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('brokerage.oversight.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerage.oversight.title')}
                    description={
                        board.scope === 'team'
                            ? t('brokerage.oversight.teamPurpose', {
                                  team: board.team ?? '',
                              })
                            : t('brokerage.oversight.purpose')
                    }
                />
                <OversightBoard board={board} routes={routes} />
            </div>
        </>
    );
}
