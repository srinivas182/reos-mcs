import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { MathLab } from '@/components/practice/math-lab';
import type { MathKind, MathProblem } from '@/components/practice/types';

/** Math lab on the web (the phone's screen 48, A9.2c). */
export default function MathPage({
    kinds,
    problem,
}: {
    kinds: { kind: MathKind; solved: number }[];
    problem: MathProblem | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('practice.math.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.math.title')}
                    description={t('practice.math.description')}
                />
                <MathLab kinds={kinds} initial={problem} />
            </div>
        </>
    );
}
