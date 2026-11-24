import { Head, Link, router } from '@inertiajs/react';
import { CalendarPlus, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import {
    type Calendar,
    type CalendarPost,
    ContentCalendar,
} from '@/components/studio/calendar';
import {
    type CelebrationList,
    Celebrations,
} from '@/components/studio/celebrations';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import {
    calendar as calendarRoute,
    index as studioRoute,
} from '@/routes/studio';
import { move as moveRoute, reuse as reuseRoute } from '@/routes/studio/posts';

/**
 * Content calendar (B12.3; part of agent web screen 17): a week or month of
 * posts to plan, move (drag, or Move) and reuse; Plan a post opens the
 * composer for the day; and today's celebration.
 */
export default function StudioCalendarPage({
    calendar,
    celebrations,
}: {
    calendar: Calendar;
    celebrations: CelebrationList;
}) {
    const { t, i18n } = useTranslation();
    const [busy, setBusy] = useState(false);
    const go = (view: 'week' | 'month', date: string) =>
        router.get(
            calendarRoute({ query: { view, date } }).url,
            {},
            { preserveScroll: true },
        );
    const reload = () => router.reload({ only: ['calendar'] });
    const day = (date: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${date}T00:00:00Z`));
    const fail = (e: unknown) =>
        toast.error(e instanceof Error ? e.message : String(e));
    const dayOf = (post: CalendarPost) =>
        calendar.days.find((d) => d.posts.some((p) => p.id === post.id))?.date;

    const move = (post: CalendarPost, date: string) => {
        const from = dayOf(post);
        if (from === date) return;
        setBusy(true);
        void postJson(moveRoute(post.id).url, { date })
            .then(() => {
                reload();
                undoToast({
                    message: t('social.calendar.moved', { date: day(date) }),
                    onUndo: async () => {
                        if (from)
                            await postJson(moveRoute(post.id).url, {
                                date: from,
                            });
                        reload();
                    },
                });
            })
            .catch(fail)
            .finally(() => setBusy(false));
    };
    const reuse = (post: CalendarPost, date: string) => {
        setBusy(true);
        void postJson(reuseRoute(post.id).url, { date })
            .then(() => {
                reload();
                toast.success(t('social.calendar.reused', { date: day(date) }));
            })
            .catch(fail)
            .finally(() => setBusy(false));
    };

    return (
        <>
            <Head title={t('social.calendar.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('social.calendar.title')}
                    description={t('social.calendar.purpose')}
                    actions={
                        <Button asChild>
                            <Link href={studioRoute().url}>
                                <CalendarPlus aria-hidden />
                                {t('social.calendar.plan')}
                            </Link>
                        </Button>
                    }
                />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div
                        role="group"
                        aria-label={t('social.calendar.title')}
                        className="flex gap-2"
                    >
                        {(['week', 'month'] as const).map((v) => (
                            <Button
                                key={v}
                                variant={
                                    calendar.view === v ? 'default' : 'outline'
                                }
                                aria-pressed={calendar.view === v}
                                onClick={() => go(v, calendar.date)}
                            >
                                {t(`social.calendar.${v}`)}
                            </Button>
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            aria-label={t('social.calendar.previous')}
                            onClick={() => go(calendar.view, calendar.previous)}
                        >
                            <ChevronLeft aria-hidden />
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                go(
                                    calendar.view,
                                    new Date().toISOString().slice(0, 10),
                                )
                            }
                        >
                            {t('social.calendar.today')}
                        </Button>
                        <Button
                            variant="outline"
                            aria-label={t('social.calendar.next')}
                            onClick={() => go(calendar.view, calendar.next)}
                        >
                            <ChevronRight aria-hidden />
                        </Button>
                    </div>
                </div>
                <p
                    className="text-base text-muted-foreground"
                    aria-live="polite"
                >
                    {`${day(calendar.from)} – ${day(calendar.to)} · ${t('social.calendar.scheduled', { count: calendar.scheduled })}`}
                </p>
                <ContentCalendar
                    calendar={calendar}
                    busy={busy}
                    onMove={move}
                    onReuse={reuse}
                    onPlan={(date) =>
                        router.get(
                            studioRoute({ query: { at: `${date}T10:00` } }).url,
                        )
                    }
                />
                <Celebrations data={celebrations} />
            </div>
        </>
    );
}
