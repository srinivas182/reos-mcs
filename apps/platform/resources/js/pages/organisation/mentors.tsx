import { Head, Link, router } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    AgentAssignments,
    MentorList,
} from '@/components/mentoring/mentor-program';
import type { MatchRow, MentorProgramData } from '@/components/mentoring/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { assign, matches, update } from '@/routes/organisation/mentors';
import { index as agentsIndex } from '@/routes/agents';

/**
 * Mentor program (A13.1a; broker web screens 21–23): mentors with focus,
 * load, price, points and rating; settings; agents and their mentors, with
 * Assign from the best matches. Agents only see this brokerage's mentors.
 */
export default function MentorProgramPage(props: MentorProgramData) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('mentoring.program.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('mentoring.program.title')}
                    description={t('mentoring.program.purpose')}
                    actions={
                        <Button asChild>
                            <Link href={agentsIndex().url}>
                                <UserPlus aria-hidden />
                                {t('mentoring.program.addMentor')}
                            </Link>
                        </Button>
                    }
                />
                <section aria-labelledby="mentors-title" className="space-y-3">
                    <h2 id="mentors-title" className="text-lg font-semibold">
                        {t('mentoring.program.mentors')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('mentoring.program.addHint')}
                    </p>
                    <MentorList
                        data={props}
                        busy={busy}
                        onSave={(mentor, values) => {
                            setBusy(true);
                            router.put(update(mentor.id).url, values, done);
                        }}
                    />
                </section>
                <section aria-labelledby="agents-title" className="space-y-3">
                    <h2 id="agents-title" className="text-lg font-semibold">
                        {t('mentoring.program.agents')}
                    </h2>
                    <AgentAssignments
                        agents={props.agents}
                        busy={busy}
                        onMatches={async (agent) => {
                            const response = await fetch(
                                matches(agent.id).url,
                                { headers: { Accept: 'application/json' } },
                            );
                            return response.ok
                                ? (
                                      (await response.json()) as {
                                          data: MatchRow[];
                                      }
                                  ).data
                                : [];
                        }}
                        onAssign={(agent, mentor) => {
                            setBusy(true);
                            router.post(
                                assign().url,
                                { agent: agent.id, mentor: mentor.id },
                                done,
                            );
                        }}
                    />
                </section>
            </div>
        </>
    );
}
