import { Head, router } from '@inertiajs/react';
import { Swords } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ChallengeCard } from '@/components/practice/challenge-card';
import type { ChallengeBoard } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { attempt as openAttempt } from '@/routes/practice';
import { store } from '@/routes/practice/challenges';

/**
 * Challenges on the web (the phone's screen 52, A9.3b): challenge a friend
 * with 10 questions by share link, and every challenge sent or accepted
 * with its board (first names only).
 */
export default function ChallengesPage({
    challenges,
}: {
    challenges: ChallengeBoard[];
}) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const create = async () => {
        setBusy(true);
        try {
            const made = await postJson<{ data: ChallengeBoard }>(store().url);
            router.reload({ only: ['challenges'] });
            setError(null);
            if (made.data.attempt_id !== null) {
                router.visit(openAttempt(made.data.attempt_id).url);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('practice.challenges.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.challenges.title')}
                    description={t('practice.challenges.description')}
                    actions={
                        <Button disabled={busy} onClick={() => void create()}>
                            <Swords aria-hidden />
                            {t('practice.challenges.create')}
                        </Button>
                    }
                />
                {error ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
                {challenges.length === 0 ? (
                    <EmptyState
                        icon={Swords}
                        title={t('practice.challenges.emptyTitle')}
                        description={t('practice.challenges.empty')}
                    />
                ) : (
                    challenges.map((c) => (
                        <ChallengeCard key={c.uuid} challenge={c} />
                    ))
                )}
            </div>
        </>
    );
}
