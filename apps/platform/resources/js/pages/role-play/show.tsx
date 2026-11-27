import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { SessionView } from '@/components/role-play/role-play-view';
import type { Session } from '@/components/role-play/types';
import { Button } from '@/components/ui/button';
import { index as learn } from '@/routes/learn';
import { assess, finish, index, say, start } from '@/routes/role-play';

/**
 * One role-play (A14.1a): the conversation with the client, the answer
 * box (speak or type), then the score and three fixes. In an assessment,
 * the next round follows.
 */
export default function RolePlaySessionPage({ session }: { session: Session }) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };
    const title =
        session.kind === 'assessment' && session.round !== null
            ? t('roleplay.assessment.round', { n: session.round, total: 5 })
            : t(`roleplay.types.${session.type}`);
    const actions =
        session.status === 'scored' ? (
            session.kind === 'assessment' ? (
                <Button
                    disabled={busy}
                    onClick={() => {
                        setBusy(true);
                        if (session.round !== null && session.round >= 5) {
                            router.visit(index().url);
                        } else {
                            router.post(assess().url, {}, done);
                        }
                    }}
                >
                    {session.round !== null && session.round >= 5
                        ? t('roleplay.seeResults')
                        : t('roleplay.nextRound')}
                </Button>
            ) : (
                <Button
                    disabled={busy}
                    onClick={() => {
                        setBusy(true);
                        router.post(
                            start().url,
                            { type: session.type, persona: session.persona },
                            done,
                        );
                    }}
                >
                    {t('roleplay.again')}
                </Button>
            )
        ) : null;

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={t(`roleplay.personas.${session.persona}.name`)}
                    back={
                        <Link
                            href={index().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('roleplay.allPractice')}
                        </Link>
                    }
                    actions={actions}
                />
                <SessionView
                    session={session}
                    busy={busy}
                    lessonHref={(lesson) =>
                        learn({ query: { topic: lesson } }).url
                    }
                    onSay={(text) => {
                        setBusy(true);
                        router.post(say(session.id).url, { text }, done);
                    }}
                    onFinish={() => {
                        setBusy(true);
                        router.post(finish(session.id).url, {}, done);
                    }}
                />
            </div>
        </>
    );
}
