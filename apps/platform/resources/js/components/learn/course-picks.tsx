import { Link } from '@inertiajs/react';
import { Headphones, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Lesson } from '@/components/role-play/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type CoursePick = {
    id: number;
    title: string;
    type: string;
    hours: number | null;
    reason: 'renewal' | 'skill' | 'mode';
    skill: Lesson | null;
    audio: boolean;
    first_lesson: number | null;
    percent: number;
};

/**
 * Courses picked for the agent (A14.2): CE first when renewal is near,
 * then the skills role-play found weakest, then the agent's mode. Each says
 * why, and whether it can be listened to on the phone (audio-only).
 */
export function CoursePicks({
    picks,
    lessonHref,
}: {
    picks: CoursePick[];
    lessonHref: (lesson: number) => string;
}) {
    const { t } = useTranslation();

    if (picks.length === 0) return null;

    return (
        <section
            aria-labelledby="course-picks"
            className="space-y-3 rounded-reos-lg border border-reos-action bg-card p-5"
        >
            <h2
                id="course-picks"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <Sparkles aria-hidden className="size-5" />
                {t('learn.picks.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('learn.picks.purpose')}
            </p>
            <ul className="grid gap-3 md:grid-cols-2">
                {picks.map((pick) => (
                    <li
                        key={pick.id}
                        className="flex flex-col gap-2 rounded-reos-md border p-4"
                    >
                        <p className="text-base font-semibold">{pick.title}</p>
                        <p className="text-base text-muted-foreground">
                            {pick.reason === 'skill' && pick.skill
                                ? t('learn.picks.skill', {
                                      skill: t(
                                          `roleplay.lessons.${pick.skill}`,
                                      ),
                                  })
                                : t(`learn.picks.${pick.reason}`)}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {pick.hours !== null ? (
                                <Badge variant="outline">
                                    {t('learn.hours', { count: pick.hours })}
                                </Badge>
                            ) : null}
                            {pick.audio ? (
                                <Badge variant="outline" className="gap-1">
                                    <Headphones
                                        aria-hidden
                                        className="size-4"
                                    />
                                    {t('learn.picks.audio')}
                                </Badge>
                            ) : null}
                        </div>
                        {pick.first_lesson !== null ? (
                            <Button
                                asChild
                                variant="outline"
                                className="mt-auto self-start"
                            >
                                <Link href={lessonHref(pick.first_lesson)}>
                                    {pick.percent > 0
                                        ? t('learn.picks.continue', {
                                              percent: pick.percent,
                                          })
                                        : t('learn.picks.start')}
                                </Link>
                            </Button>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
