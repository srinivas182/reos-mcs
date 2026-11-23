import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { GroupCard } from '@/components/practice/group-card';
import type { StudyGroup } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { postJson, putJson } from '@/lib/content';
import { challenges } from '@/routes/practice';
import { join, optIn } from '@/routes/practice/groups';

/** A study group opened from its invite link, signed in (screen 52, A9.3c): Join, after turning study groups on. */
export default function GroupPage({
    group,
    opt_in,
}: {
    group: StudyGroup;
    opt_in: boolean;
}) {
    const { t } = useTranslation();
    const [on, setOn] = useState(opt_in);
    const [error, setError] = useState<string | null>(null);
    const send = async (go: () => Promise<unknown>, then?: () => void) => {
        try {
            await go();
            setError(null);
            then?.();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={group.name} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.groups.title')}
                    description={t('practice.groups.joinBody', {
                        name: group.name,
                        count: group.members,
                    })}
                />
                {error ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
                {!on && !group.mine ? (
                    <div className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                        <p className="text-body">
                            {t('practice.groups.optInBody')}
                        </p>
                        <Button
                            onClick={() =>
                                void send(
                                    () =>
                                        putJson(optIn().url, { opt_in: true }),
                                    () => setOn(true),
                                )
                            }
                        >
                            {t('practice.groups.turnOn')}
                        </Button>
                    </div>
                ) : null}
                <GroupCard
                    group={group}
                    onJoin={
                        on
                            ? () =>
                                  void send(
                                      () => postJson(join(group.uuid).url),
                                      () => router.visit(challenges().url),
                                  )
                            : undefined
                    }
                />
            </div>
        </>
    );
}
