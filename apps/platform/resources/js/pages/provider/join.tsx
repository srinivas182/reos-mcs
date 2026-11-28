import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { CategoryStep } from '@/components/providers/category-step';
import type { CategoryCard, Joining } from '@/components/providers/types';
import { category, fields } from '@/routes/provider-join';
import { request } from '@/routes/provider-join/categories';

const routes = {
    request: request().url,
    category: category().url,
    fields: fields().url,
};

/**
 * Join the provider network (B15.1, provider web screen 2 "What do you
 * do?"): categories, their licence step and profile fields from the super
 * admin's settings. B15.2 adds the welcome, licence, areas, plan and rules.
 */
export default function ProviderJoin({
    categories,
    joining,
}: {
    categories: CategoryCard[];
    joining: Joining;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('providers.join.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providers.join.title')}
                    description={t('providers.join.purpose')}
                />
                <CategoryStep
                    categories={categories}
                    joining={joining}
                    routes={routes}
                />
            </div>
        </>
    );
}
