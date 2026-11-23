import { Head, router } from '@inertiajs/react';
import { Swords, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ChallengeCard } from '@/components/practice/challenge-card';
import { GroupCard } from '@/components/practice/group-card';
import type { ChallengeBoard, StudyGroup } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson, putJson } from '@/lib/content';
import { attempt as openAttempt } from '@/routes/practice';
import { accept as acceptChallenge, store } from '@/routes/practice/challenges';
import {
    challenge as groupChallenge,
    leave,
    optIn as optInRoute,
    store as storeGroup,
} from '@/routes/practice/groups';

/**
 * Challenges and study groups on the web (the phone's screen 52, A9.3b and
 * A9.3c): challenge a friend with 10 questions by share link; every
 * challenge sent, accepted or sent to a group, with its board; and study
 * groups with their weekly leaderboard (first names only), once turned on.
 */
export default function ChallengesPage({
    challenges,
    opt_in,
    groups,
}: {
    challenges: ChallengeBoard[];
    opt_in: boolean;
    groups: StudyGroup[];
}) {
    const { t } = useTranslation();
    const [name, setName] = useState('');
    const refresh = () =>
        router.reload({ only: ['challenges', 'groups', 'opt_in'] });
    const run = async (send: () => Promise<unknown>) => {
        try {
            await send();
            setError(null);
            refresh();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const challengeGroup = async (group: StudyGroup) => {
        try {
            const made = await postJson<{ data: ChallengeBoard }>(
                groupChallenge(group.uuid).url,
            );
            if (made.data.attempt_id !== null) {
                router.visit(openAttempt(made.data.attempt_id).url);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
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
                        <ChallengeCard
                            key={c.uuid}
                            challenge={c}
                            onAccept={() =>
                                void postJson<{ data: ChallengeBoard }>(
                                    acceptChallenge(c.uuid).url,
                                ).then((taken) =>
                                    taken.data.attempt_id !== null
                                        ? router.visit(
                                              openAttempt(taken.data.attempt_id)
                                                  .url,
                                          )
                                        : undefined,
                                )
                            }
                        />
                    ))
                )}
                <section aria-labelledby="groups" className="space-y-4">
                    <div className="space-y-1">
                        <h2 id="groups" className="text-xl font-semibold">
                            {t('practice.groups.title')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('practice.groups.description')}
                        </p>
                    </div>
                    {!opt_in ? (
                        <div className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                            <p className="text-body">
                                {t('practice.groups.off')}
                            </p>
                            <Button
                                onClick={() =>
                                    void run(() =>
                                        putJson(optInRoute().url, {
                                            opt_in: true,
                                        }),
                                    )
                                }
                            >
                                {t('practice.groups.turnOn')}
                            </Button>
                        </div>
                    ) : (
                        <>
                            <form
                                className="flex flex-wrap items-end gap-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    void run(() =>
                                        postJson(storeGroup().url, { name }),
                                    ).then(() => setName(''));
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="group-name">
                                        {t('practice.groups.name')}
                                    </Label>
                                    <Input
                                        id="group-name"
                                        value={name}
                                        maxLength={80}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={name.trim().length < 2}
                                >
                                    {t('practice.groups.create')}
                                </Button>
                            </form>
                            {groups.length === 0 ? (
                                <EmptyState
                                    icon={Users}
                                    title={t('practice.groups.emptyTitle')}
                                    description={t('practice.groups.empty')}
                                />
                            ) : (
                                groups.map((g) => (
                                    <GroupCard
                                        key={g.uuid}
                                        group={g}
                                        onChallenge={() =>
                                            void challengeGroup(g)
                                        }
                                        onLeave={() =>
                                            void run(() =>
                                                postJson(leave(g.uuid).url),
                                            )
                                        }
                                    />
                                ))
                            )}
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
