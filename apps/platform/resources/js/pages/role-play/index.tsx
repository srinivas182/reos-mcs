import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AssessmentCard,
    PracticePicker,
    RecentList,
} from '@/components/role-play/role-play-view';
import type { Overview } from '@/components/role-play/types';
import { PageHeader } from '@/components/page-header';
import { assess, show, start } from '@/routes/role-play';

/**
 * Role-play (A14.1a; agent screen 7 for the skills assessment): the
 * assessment, the five skills to practise with their people, and recent
 * practice. Private to the agent.
 */
export default function RolePlayPage({ types, recent, assessment }: Overview) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('roleplay.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('roleplay.title')}
                    description={t('roleplay.purpose')}
                />
                <AssessmentCard
                    assessment={assessment}
                    busy={busy}
                    onAssess={() => {
                        setBusy(true);
                        router.post(assess().url, {}, done);
                    }}
                />
                <section aria-labelledby="practice-title" className="space-y-3">
                    <h2 id="practice-title" className="text-lg font-semibold">
                        {t('roleplay.practiceTitle')}
                    </h2>
                    <PracticePicker
                        types={types}
                        busy={busy}
                        onStart={(type, persona) => {
                            setBusy(true);
                            router.post(start().url, { type, persona }, done);
                        }}
                    />
                </section>
                <section aria-labelledby="recent-title" className="space-y-3">
                    <h2 id="recent-title" className="text-lg font-semibold">
                        {t('roleplay.recent')}
                    </h2>
                    <RecentList recent={recent} href={(id) => show(id).url} />
                    <p className="text-base text-muted-foreground">
                        {t('roleplay.private')}
                    </p>
                </section>
            </div>
        </>
    );
}
