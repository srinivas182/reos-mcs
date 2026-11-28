import { Head } from '@inertiajs/react';
import { BookOpen, Megaphone } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrokerageRules } from '@/components/brokerage/brokerage-rules';
import type { PoliciesData } from '@/components/brokerage/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as advertising } from '@/routes/organisation/advertising';
import { index as virtualBroker } from '@/routes/organisation/virtual-broker';
import { check } from '@/routes/policies';
import { destroy, restore, store, update } from '@/routes/policies/rules';

const routes = {
    check: check().url,
    store: store().url,
    update: (id: number) => update(id).url,
    destroy: (id: number) => destroy(id).url,
    restore: (id: number) => restore(id).url,
};

const card = 'space-y-3 rounded-reos-lg border border-border bg-card p-5';

/**
 * Brokerage › Policies (A15.2, broker web screen 35 with broker journey
 * screens 5 and 6): the policy manual, the advertising policy and the
 * brokerage's own rules, each checked against TREC.
 */
export default function Policies({ policies }: { policies: PoliciesData }) {
    const { t } = useTranslation();
    const ads = policies.advertising;

    return (
        <>
            <Head title={t('brokerage.policies.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brokerage.policies.title')}
                    description={t('brokerage.policies.purpose')}
                />
                <div className="grid gap-4 md:grid-cols-2">
                    <section aria-labelledby="policy-manual" className={card}>
                        <h2
                            id="policy-manual"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <BookOpen aria-hidden className="size-5" />
                            {t('brokerage.policies.manual')}
                        </h2>
                        <p className="text-base">
                            {policies.manual
                                ? t('brokerage.policies.manualVersion', {
                                      version: policies.manual.version,
                                      file: policies.manual.file_name,
                                  })
                                : t('brokerage.policies.noManual')}
                        </p>
                        {policies.manual ? (
                            <p className="text-base text-muted-foreground">
                                {t('brokerage.policies.manualConflicts', {
                                    count: policies.manual.conflicts,
                                })}
                            </p>
                        ) : null}
                        <Button variant="outline" asChild>
                            <a
                                href={virtualBroker().url}
                                aria-label={`${t('brokerage.policies.open')}: ${t('brokerage.policies.manual')}`}
                            >
                                {t('brokerage.policies.open')}
                            </a>
                        </Button>
                    </section>
                    <section aria-labelledby="policy-ads" className={card}>
                        <h2
                            id="policy-ads"
                            className="flex items-center gap-2 font-semibold"
                        >
                            <Megaphone aria-hidden className="size-5" />
                            {t('brokerage.policies.advertising')}
                        </h2>
                        <p className="text-base">
                            {ads.approval_all
                                ? t('brokerage.policies.approvalAll')
                                : ads.approval_new_agents
                                  ? t('brokerage.policies.approvalNew')
                                  : t('brokerage.policies.approvalNone')}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {t('brokerage.policies.banned', {
                                count: ads.banned,
                            })}
                        </p>
                        <Button variant="outline" asChild>
                            <a
                                href={advertising().url}
                                aria-label={`${t('brokerage.policies.open')}: ${t('brokerage.policies.advertising')}`}
                            >
                                {t('brokerage.policies.open')}
                            </a>
                        </Button>
                    </section>
                </div>
                <BrokerageRules
                    rules={policies.rules}
                    canManage={policies.can_manage}
                    routes={routes}
                />
            </div>
        </>
    );
}
