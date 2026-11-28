import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { JoinWizard } from '@/components/providers/join-wizard';
import type { CategoryCard, Onboarding } from '@/components/providers/types';
import {
    business,
    category,
    fields,
    plan,
    rules,
    status,
    welcome,
} from '@/routes/provider-join';
import { destroy, store } from '@/routes/provider-join/areas';
import { request } from '@/routes/provider-join/categories';
import { store as licence } from '@/routes/provider-licences';

const routes = {
    request: request().url,
    category: category().url,
    fields: fields().url,
    status: status().url,
    welcome: welcome().url,
    business: business().url,
    licence: licence().url,
    areas: store().url,
    area: (id: number) => destroy(id).url,
    plan: plan().url,
    rules: rules().url,
};

/**
 * Join the provider network (B15.1 and B15.2, provider screens 1–6):
 * welcome, what you do, licence, service areas, plan on reos.com and the
 * network rules, with the profile's status at the top.
 */
export default function ProviderJoin({
    categories,
    onboarding,
}: {
    categories: CategoryCard[];
    onboarding: Onboarding;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('providers.onboarding.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.onboarding.title')}
                    description={t('providers.onboarding.purpose')}
                />
                <JoinWizard
                    categories={categories}
                    onboarding={onboarding}
                    routes={routes}
                />
            </div>
        </>
    );
}
