import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { ChallengeList, PodPanel } from '@/components/pods/pod-view';
import type { ChallengeRow, PodView } from '@/components/pods/types';
import { PageHeader } from '@/components/page-header';
import { undoToast } from '@/lib/undo-toast';
import {
    join as joinChallenge,
    leave as leaveChallenge,
} from '@/routes/brokerage-challenges';
import { index as messagesIndex } from '@/routes/messages';
import { goal, join, leave, rejoin } from '@/routes/pod';
import { show as showSession } from '@/routes/sessions';

/**
 * Pod and challenges (A13.2; agent web screen 131): the accountability pod —
 * weekly goals, the members' shared numbers, the 15-minute call and pod
 * messages — and the brokerage's opt-in challenges with two boards.
 */
export default function PodPage({
    pod,
    challenges,
}: {
    pod: PodView;
    challenges: ChallengeRow[];
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };
    const session = pod.pod?.call.session ?? null;
    const conversation = pod.pod?.conversation ?? null;

    return (
        <>
            <Head title={t('nav.podAndChallenges')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('pods.title')}
                    description={t('pods.purpose')}
                />
                <PodPanel
                    pod={pod}
                    busy={busy}
                    callHref={
                        session === null ? null : showSession(session).url
                    }
                    messagesHref={
                        conversation === null
                            ? null
                            : messagesIndex({ query: { c: conversation } }).url
                    }
                    onJoin={() => {
                        setBusy(true);
                        router.post(join().url, {}, done);
                    }}
                    onLeave={() => {
                        const from = pod.pod?.id ?? null;
                        setBusy(true);
                        router.post(
                            leave().url,
                            {},
                            {
                                ...done,
                                onSuccess: () =>
                                    from === null
                                        ? undefined
                                        : undoToast({
                                              message: t('pods.left'),
                                              onUndo: () =>
                                                  new Promise<void>((resolve) =>
                                                      router.post(
                                                          rejoin().url,
                                                          { pod: from },
                                                          {
                                                              preserveScroll: true,
                                                              onFinish: () =>
                                                                  resolve(),
                                                          },
                                                      ),
                                                  ),
                                          }),
                            },
                        );
                    }}
                    onGoal={(text) => {
                        setBusy(true);
                        router.put(
                            goal().url,
                            { text },
                            {
                                ...done,
                                onSuccess: () => toast.success(t('pods.saved')),
                            },
                        );
                    }}
                />
                <section
                    aria-labelledby="challenges-title"
                    className="space-y-3"
                >
                    <div className="space-y-1">
                        <h2
                            id="challenges-title"
                            className="text-lg font-semibold"
                        >
                            {t('pods.challenges.title')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('pods.challenges.purpose')}
                        </p>
                    </div>
                    <ChallengeList
                        challenges={challenges}
                        busy={busy}
                        onJoin={(c) => {
                            setBusy(true);
                            router.post(
                                joinChallenge(c.id).url,
                                {},
                                {
                                    ...done,
                                    onSuccess: () =>
                                        toast.success(
                                            t('pods.challenges.joined'),
                                        ),
                                },
                            );
                        }}
                        onLeave={(c) => {
                            setBusy(true);
                            router.post(
                                leaveChallenge(c.id).url,
                                {},
                                {
                                    ...done,
                                    onSuccess: () =>
                                        undoToast({
                                            message: t(
                                                'pods.challenges.leftOne',
                                            ),
                                            onUndo: () =>
                                                new Promise<void>((resolve) =>
                                                    router.post(
                                                        joinChallenge(c.id).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                            onFinish: () =>
                                                                resolve(),
                                                        },
                                                    ),
                                                ),
                                        }),
                                },
                            );
                        }}
                    />
                </section>
            </div>
        </>
    );
}
