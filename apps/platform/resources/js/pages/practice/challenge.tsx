import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { ChallengeCard } from '@/components/practice/challenge-card';
import type { ChallengeBoard } from '@/components/practice/types';
import { postJson } from '@/lib/content';
import { attempt as openAttempt } from '@/routes/practice';
import { accept } from '@/routes/practice/challenges';

/** A challenge opened from its share link, signed in (screen 52, A9.3b): Accept, then the same 10 questions. */
export default function ChallengePage({
    challenge,
}: {
    challenge: ChallengeBoard;
}) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const take = async () => {
        try {
            const taken = await postJson<{ data: ChallengeBoard }>(
                accept(challenge.uuid).url,
            );
            if (taken.data.attempt_id !== null) {
                router.visit(openAttempt(taken.data.attempt_id).url);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('practice.challenges.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.challenges.title')}
                    description={t('practice.challenges.acceptBody', {
                        name: challenge.from,
                        count: challenge.questions,
                    })}
                />
                {error ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
                <ChallengeCard
                    challenge={challenge}
                    onAccept={() => void take()}
                />
            </div>
        </>
    );
}
