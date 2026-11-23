import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PlanView } from '@/components/agents/plan-view';
import { ScorecardCard } from '@/components/agents/scorecard-card';
import type { AgentPlan, PlanTask, Scorecard } from '@/components/agents/types';
import { PageHeader } from '@/components/page-header';
import { postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import {
    promise as promiseRoute,
    tick as tickRoute,
} from '@/routes/agent/plan';

/**
 * My 90-day plan (A11.1b; agent screens 10 and 110): the plan for the
 * agent's goal with this week's tasks, and the weekly scorecard.
 */
export default function AgentPlanPage({
    plan: initial,
    scorecard,
}: {
    plan: AgentPlan;
    scorecard: Scorecard;
}) {
    const { t } = useTranslation();
    const [plan, setPlan] = useState(initial);
    const [error, setError] = useState<string | null>(null);
    const run = async (go: () => Promise<{ data: AgentPlan }>) => {
        try {
            setPlan((await go()).data);
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const tick = (task: PlanTask, done: boolean) => {
        const send = (value: boolean) =>
            run(() =>
                putJson<{ data: AgentPlan }>(tickRoute(task.code).url, {
                    week: task.week,
                    done: value,
                }),
            );
        void send(done);
        undoToast({
            message: t(done ? 'agents.plan.ticked' : 'agents.plan.unticked'),
            onUndo: () => send(!done),
        });
    };

    return (
        <>
            <Head title={t('agents.plan.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('agents.plan.title')}
                    description={t('agents.plan.description')}
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <PlanView
                    plan={plan}
                    onTick={tick}
                    onPromise={() =>
                        void run(() =>
                            postJson<{ data: AgentPlan }>(promiseRoute().url),
                        )
                    }
                />
                <ScorecardCard scorecard={scorecard} />
            </div>
        </>
    );
}
