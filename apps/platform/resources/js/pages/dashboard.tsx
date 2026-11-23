import { Head, Link, router, usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { firstName } from '@/lib/person-name';
import { useTranslation } from 'react-i18next';
import { AgentToday } from '@/components/agents/agent-today';
import { ModeCard } from '@/components/agents/mode-card';
import type {
    AgentTodayData,
    ModeState,
    TodayItem,
} from '@/components/agents/types';
import { CeHoursTile } from '@/components/ce-requirement';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { ApprovalsCard } from '@/components/approvals/approvals-card';
import type { ApprovalSummary } from '@/components/approvals/types';
import { TeachingCard } from '@/components/builder/teaching-card';
import type { Teaching } from '@/components/builder/teaching-card';
import { SessionCard } from '@/components/sessions/session-card';
import { TodayCard } from '@/components/today-card';
import { CandidateToday } from '@/components/practice/candidate-today';
import type { StudyToday } from '@/components/practice/types';
import { activeMembership, roleName } from '@/lib/roles';
import { deleteJson, postJson, putJson } from '@/lib/content';
import {
    brief as agentBrief,
    plan as agentPlan,
    setup as agentSetup,
} from '@/routes/agent';
import { tick as tickPlan } from '@/routes/agent/plan';
import { done as todoDone, undone as todoUndone } from '@/routes/today/todos';
import { goal as goalRoute, snooze as snoozeRoute } from '@/routes/agent/mode';
import { edit as editGoal } from '@/routes/goal';
import { index as sessionsIndex } from '@/routes/sessions';
import type { CeRequirement } from '@/types';
import type { CalendarDay } from '@/types/calendar';

/** Done at the source: a to-do (and so its CRM task or deal deadline) or a plan task. */
function markDone(item: TodayItem, done: boolean): Promise<unknown> {
    if (item.plan) {
        return putJson(tickPlan(item.plan.code).url, {
            week: item.plan.week,
            done,
        });
    }
    if (item.todo_id === null) return Promise.resolve();
    return done
        ? postJson(todoDone(item.todo_id).url)
        : deleteJson(todoUndone(item.todo_id).url);
}

/**
 * Each role's dashboard (web journey screens 4, 12, 24, 30, 42, 49, 54, 58)
 * is built in that role's sprint. Agents (screen 12) have their CE hours
 * (A5.2) and today's calendar (B4.1); candidates (screen 4) their readiness,
 * streak, exam date, Continue and today's plan (A8.3); agents also see Today
 * as one ranked list with their mode's stats (A11.1b). Until the other cards arrive: the journey's greeting and an honest
 * note instead of placeholder boxes.
 */
export default function Dashboard({
    today = null,
    ce = null,
    approvals = null,
    teaching = null,
    study = null,
    mode = null,
    agentToday = null,
}: {
    today?: CalendarDay | null;
    ce?: CeRequirement | null;
    approvals?: ApprovalSummary | null;
    teaching?: Teaching | null;
    study?: StudyToday | null;
    mode?: ModeState | null;
    agentToday?: AgentTodayData | null;
}) {
    const { t } = useTranslation();
    const { auth, sessions } = usePage().props;
    const current = activeMembership(auth.roles);
    const first = firstName(auth.user.name);
    const title = first
        ? t('dashboard.greeting', { name: first })
        : t('dashboard.greetingNoName');

    return (
        <>
            <Head title={t('nav.dashboard')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={
                        current
                            ? t('dashboard.description', {
                                  role: roleName(t, current.role),
                              })
                            : undefined
                    }
                />
                {mode ? (
                    <ModeCard
                        mode={mode}
                        goalHref={editGoal().url}
                        setupHref={agentSetup().url}
                        onSwitch={(goal) =>
                            void putJson(goalRoute().url, {
                                goal,
                                reason: 'suggested',
                            }).then(() => router.reload({ only: ['mode'] }))
                        }
                        onSnooze={() =>
                            void postJson(snoozeRoute().url).then(() =>
                                router.reload({ only: ['mode'] }),
                            )
                        }
                    />
                ) : null}
                {agentToday ? (
                    <AgentToday
                        today={agentToday}
                        planUrl={agentPlan().url}
                        briefUrl={agentBrief().url}
                        onDone={(item, done) => markDone(item, done)}
                    />
                ) : null}
                {ce ? (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <CeHoursTile ce={ce} />
                    </div>
                ) : null}
                {approvals ? <ApprovalsCard summary={approvals} /> : null}
                {teaching ? <TeachingCard teaching={teaching} /> : null}
                {study ? <CandidateToday study={study} /> : null}
                {today ? <TodayCard today={today} /> : null}
                {sessions ? (
                    <section className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                {t('sessions.next')}
                            </h2>
                            <Link
                                href={sessionsIndex().url}
                                className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {t('sessions.title')}
                            </Link>
                        </div>
                        {sessions.next ? (
                            <SessionCard
                                session={sessions.next}
                                me={auth.user.id}
                            />
                        ) : (
                            <p className="text-base text-muted-foreground">
                                {t('sessions.empty')}
                            </p>
                        )}
                    </section>
                ) : null}
                {study || agentToday ? null : (
                    <EmptyState
                        icon={LayoutDashboard}
                        title={t('dashboard.comingTitle')}
                        description={t('dashboard.comingDescription')}
                    />
                )}
            </div>
        </>
    );
}
