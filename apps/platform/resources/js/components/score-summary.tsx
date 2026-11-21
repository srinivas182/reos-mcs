import { useTranslation } from 'react-i18next';
import type { QuizTopic } from '@/components/quiz-card';
import { cn } from '@/lib/utils';

export type TopicLevel = 'strong' | 'learning' | 'start_here';

const levelTone: Record<TopicLevel, string> = {
    strong: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    learning: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    start_here: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

const order: Record<TopicLevel, number> = {
    strong: 0,
    learning: 1,
    start_here: 2,
};

/**
 * "You already know 40%" (candidate journey screen 7): the ring, the line
 * under it and up to three topics, strongest first.
 */
export function ScoreSummary({
    correct,
    total,
    topics,
}: {
    correct: number;
    total: number;
    topics: { topic: QuizTopic; level: TopicLevel }[];
}) {
    const { t } = useTranslation();
    const percent = Math.round((100 * correct) / Math.max(1, total));
    const radius = 70;
    const circumference = 2 * Math.PI * radius;
    const shown = [...topics]
        .sort((a, b) => order[a.level] - order[b.level])
        .slice(0, 3);

    return (
        <div className="space-y-6">
            <div className="flex justify-center">
                <div
                    role="img"
                    aria-label={`${percent}% ${t('onboarding.score.label')}`}
                    className="relative size-44"
                >
                    <svg viewBox="0 0 160 160" className="size-full -rotate-90">
                        <circle
                            cx="80"
                            cy="80"
                            r={radius}
                            fill="none"
                            strokeWidth="12"
                            className="stroke-border"
                        />
                        <circle
                            cx="80"
                            cy="80"
                            r={radius}
                            fill="none"
                            strokeWidth="12"
                            strokeLinecap="round"
                            strokeDasharray={circumference}
                            strokeDashoffset={
                                circumference * (1 - percent / 100)
                            }
                            className="stroke-primary"
                        />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-5xl font-bold text-foreground">
                            {percent}%
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {t('onboarding.score.label')}
                        </span>
                    </div>
                </div>
            </div>
            <h1 className="text-center text-3xl font-bold tracking-tight text-foreground">
                {percent >= 50
                    ? t('onboarding.score.strong')
                    : t('onboarding.score.start')}
            </h1>
            {shown.length > 0 ? (
                <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                    {shown.map(({ topic, level }) => (
                        <li
                            key={topic}
                            className="flex min-h-14 items-center gap-3 px-5 py-3"
                        >
                            <span className="flex-1 font-semibold text-foreground">
                                {t(`onboarding.topics.${topic}`)}
                            </span>
                            <span
                                className={cn(
                                    'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                    levelTone[level],
                                )}
                            >
                                {t(`onboarding.levels.${level}`)}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
