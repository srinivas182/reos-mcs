import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    OpenHousesView,
    type OpenHouse,
    type OpenHouseVisit,
} from '@/components/open-houses/open-houses-view';
import { PageHeader } from '@/components/page-header';
import { index as contacts } from '@/routes/contacts';
import { store, visits as visitsRoute } from '@/routes/open-houses';

const routes = {
    create: store().url,
    visits: (id: string) => visitsRoute(id).url,
    contact: (uuid: string) => contacts({ query: { contact: uuid } }).url,
};

/** Open houses (B14.3a): start sign-in, show the QR code, see who came. */
export default function OpenHousesPage({
    houses,
    visits,
}: {
    houses: OpenHouse[];
    visits: OpenHouseVisit[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('leads.openHouse.pageTitle')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('leads.openHouse.pageTitle')}
                    description={t('leads.openHouse.pagePurpose')}
                />
                <OpenHousesView
                    houses={houses}
                    visits={visits}
                    routes={routes}
                />
            </div>
        </>
    );
}
