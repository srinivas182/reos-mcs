import { Link, router } from '@inertiajs/react';
import { ArrowRight, Dumbbell, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    attempt as openAttempt,
    flashcards,
    math as mathLab,
    readiness,
    speed,
    start,
} from '@/routes/practice';
import { lesson as openLesson } from '@/routes/learn';
import { show as showPlan, store as storePlan } from '@/routes/practice/plans';
import type { Coach } from './types';

/**
 * The top of the practice hub (screen 38, A9.2b): the smart drill on the
 * three weakest topics, then every mode ranked by what helps most, each
 * with why.
 */
export function CoachPanel({ coach }: { coach: Coach }) {
    const { t, i18n } = useTranslation();
    const topics = new Intl.ListFormat(i18n.language, {
        style: 'long',
        type: 'conjunction',
    }).format(coach.drill.topics.map((x) => t(`practice.topics.${x}`)));
    const drill = () =>
        coach.drill.open !== null
            ? router.visit(openAttempt(coach.drill.open).url)
            : router.post(start().url, { kind: 'drill' });
    const go = (item: Coach['ranked'][number]) => {
        switch (item.mode) {
            case 'diagnostic':
                return router.post(start().url, { kind: 'diagnostic' });
            case 'plan':
                return item.plan_id !== null
                    ? router.visit(showPlan(item.plan_id).url)
                    : undefined;
            case 'drill':
                return drill();
            case 'fix':
                return router.post(storePlan().url, { topic: item.topic });
            case 'math':
                return router.visit(mathLab().url);
            case 'flashcards':
                return router.visit(flashcards().url);
            case 'speed':
                return router.visit(speed().url);
            case 'exam_success':
                return item.lesson_id !== null
                    ? router.visit(openLesson(item.lesson_id).url)
                    : undefined;
            case 'simulation':
                return document
                    .getElementById('simulations')
                    ?.scrollIntoView({ behavior: 'smooth' });
            default:
                return router.visit(readiness().url);
        }
    };
    const plan = (id: number | null) => coach.plans.find((p) => p.id === id);

    return (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section
                aria-labelledby="drill"
                className="space-y-3 rounded-reos-lg border border-primary bg-card p-5"
            >
                <h2
                    id="drill"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Dumbbell aria-hidden className="size-5 text-primary" />
                    {t('practice.drill.title')}
                </h2>
                <p className="text-body text-muted-foreground">
                    {t('practice.drill.description', {
                        count: coach.drill.questions,
                    })}
                </p>
                <p className="text-body">
                    {t('practice.drill.topics', { topics })}
                </p>
                {coach.drill.misses > 0 ? (
                    <p className="text-body text-muted-foreground">
                        {t('practice.drill.misses', {
                            count: coach.drill.misses,
                        })}
                    </p>
                ) : null}
                <Button onClick={drill}>
                    {coach.drill.open !== null
                        ? t('practice.drill.carryOn')
                        : t('practice.drill.start')}
                </Button>
            </section>

            <section
                aria-labelledby="ranked"
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            >
                <h2
                    id="ranked"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Sparkles aria-hidden className="size-5 text-primary" />
                    {t('practice.coach.title')}
                </h2>
                <p className="text-body text-muted-foreground">
                    {t('practice.coach.description')}
                </p>
                <ol className="divide-y divide-border">
                    {coach.ranked.map((item, i) => {
                        const p = plan(item.plan_id);
                        const name =
                            item.mode === 'plan' && p
                                ? t('practice.fix.carryOn', {
                                      topic: t(`practice.topics.${p.topic}`),
                                      done: p.done,
                                      steps: p.steps,
                                  })
                                : item.mode === 'flashcards' &&
                                    item.count !== null
                                  ? t('practice.coach.modes.flashcardsDue', {
                                        count: item.count,
                                    })
                                  : item.mode === 'exam_success' &&
                                      item.count !== null
                                    ? t(
                                          'practice.coach.modes.examSuccessLeft',
                                          {
                                              count: item.count,
                                          },
                                      )
                                    : item.mode === 'fix' && item.topic
                                      ? t('practice.fix.start', {
                                            topic: t(
                                                `practice.topics.${item.topic}`,
                                            ),
                                        })
                                      : t(`practice.coach.modes.${item.mode}`);

                        return (
                            <li
                                key={`${item.mode}-${item.plan_id ?? item.topic ?? i}`}
                                className="flex items-center justify-between gap-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">{name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            `practice.coach.why.${item.why as 'map'}`,
                                        )}
                                    </p>
                                </div>
                                {item.mode === 'readiness' ? (
                                    <Button
                                        asChild
                                        variant="outline"
                                        aria-label={name}
                                    >
                                        <Link href={readiness()}>
                                            <ArrowRight aria-hidden />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        aria-label={name}
                                        onClick={() => go(item)}
                                    >
                                        <ArrowRight aria-hidden />
                                    </Button>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </section>
        </div>
    );
}
