import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ReadinessView } from '@/components/practice/readiness-view';
import type { ReadinessMap } from '@/components/practice/types';

/** Readiness (candidate web sidebar; phone screens 54 and 44, A9.2). */
export default function ReadinessPage({ map }: { map: ReadinessMap }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('practice.readinessMap.title')} />
            <ReadinessView map={map} />
        </>
    );
}
