import { Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    BookOpen,
    CalendarDays,
    CalendarPlus,
    Home,
    Lock,
    RefreshCw,
    SquareCheck,
    TriangleAlert,
    Users,
    Video,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AppointmentDialog } from '@/components/appointment-dialog';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { eventTime, eventTitle, shortDate, sourceName } from '@/lib/calendar';
import { cn } from '@/lib/utils';
import { index as connections } from '@/routes/connections';
import { undo } from '@/routes/calendar';
import type {
    CalendarDay,
    CalendarEvent,
    CalendarKind,
} from '@/types/calendar';

const ICONS: Record<CalendarKind, LucideIcon> = {
    appointment: Users,
    showing: Home,
    deadline: AlertTriangle,
    session: Video,
    ce: BookOpen,
    todo: SquareCheck,
    event: CalendarDays,
};

/**
 * "Today" on the agent dashboard (web journey screen 12): the day's events
 * with the tool each came from and when, New appointment, and any item
 * opens. Events from a calendar shared as busy only show as "Busy"; one not
 * yet saved to the tool, or changed in two places, says so on its row.
 */
export function TodayCard({ today }: { today: CalendarDay }) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState<CalendarEvent | 'new' | null>(null);
    const now = Date.now();

    return (
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <CardTitle className="text-lg">{t('calendar.today')}</CardTitle>
                <Button onClick={() => setOpen('new')}>
                    <CalendarPlus aria-hidden className="size-5" />
                    {t('calendar.newAppointment')}
                </Button>
            </CardHeader>
            <CardContent className="space-y-3">
                {today.removed.map((removed) => (
                    <div
                        key={removed.conflict}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md bg-reos-tone-warning-bg p-3"
                    >
                        <div>
                            <p className="font-semibold text-reos-tone-warning-fg">
                                {t('calendar.removed.title')}
                            </p>
                            <p>
                                {t('calendar.removed.body', {
                                    title:
                                        removed.title ?? t('calendar.untitled'),
                                    date: shortDate(
                                        new Date(
                                            new Date(
                                                removed.at ?? now,
                                            ).getTime() +
                                                30 * 86_400_000,
                                        ).toISOString(),
                                        i18n.language,
                                    ),
                                })}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    undo(removed.conflict).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('calendar.removed.bringBack')}
                        </Button>
                    </div>
                ))}

                {today.data.length === 0 ? (
                    <EmptyState
                        icon={CalendarDays}
                        title={t('calendar.empty')}
                        description={t('calendar.emptyBody')}
                        action={
                            <Button onClick={() => setOpen('new')}>
                                {t('calendar.newAppointment')}
                            </Button>
                        }
                        secondaryAction={
                            <Button variant="ghost" asChild>
                                <Link href={connections().url}>
                                    {t('calendar.connect')}
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <table className="w-full text-left">
                        <caption className="sr-only">
                            {t('calendar.todayAgenda')}
                        </caption>
                        <thead>
                            <tr className="text-sm tracking-wide text-muted-foreground uppercase">
                                <th scope="col" className="w-8 pb-2">
                                    <span className="sr-only">
                                        {t('calendar.fields.kind')}
                                    </span>
                                </th>
                                <th scope="col" className="pb-2 font-medium">
                                    {t('calendar.what')}
                                </th>
                                <th
                                    scope="col"
                                    className="hidden pb-2 font-medium sm:table-cell"
                                >
                                    {t('calendar.from')}
                                </th>
                                <th
                                    scope="col"
                                    className="pb-2 text-right font-medium"
                                >
                                    {t('calendar.when')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {today.data.map((event) => {
                                const Icon = event.busy
                                    ? Lock
                                    : ICONS[event.kind];
                                const past =
                                    !event.all_day &&
                                    new Date(event.ends_at).getTime() < now;

                                return (
                                    <tr
                                        key={event.id}
                                        className={cn(
                                            'border-t border-border',
                                            past && 'text-muted-foreground',
                                        )}
                                    >
                                        <td className="py-2 align-middle">
                                            <Icon
                                                aria-hidden
                                                className={cn(
                                                    'size-5',
                                                    event.kind === 'deadline'
                                                        ? 'text-reos-tone-danger-fg'
                                                        : 'text-muted-foreground',
                                                )}
                                            />
                                        </td>
                                        <td className="py-1">
                                            <button
                                                type="button"
                                                onClick={() => setOpen(event)}
                                                className="flex min-h-12 w-full flex-col items-start justify-center rounded-reos-sm text-left hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            >
                                                <span className="font-medium">
                                                    {eventTitle(event, t)}
                                                </span>
                                                <RowNote event={event} />
                                            </button>
                                        </td>
                                        <td className="hidden py-2 text-muted-foreground sm:table-cell">
                                            {sourceName(event, t)}
                                        </td>
                                        <td className="py-2 text-right">
                                            <span className="inline-flex rounded-reos-pill bg-reos-tone-info-bg px-2.5 py-0.5 text-sm font-medium whitespace-nowrap text-reos-tone-info-fg">
                                                {eventTime(
                                                    event,
                                                    t,
                                                    i18n.language,
                                                    today.timezone,
                                                )}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
                <p className="text-sm text-muted-foreground">
                    {today.write_to
                        ? t('calendar.writeTo', {
                              calendar: today.write_to.calendar,
                              tool: today.write_to.tool,
                          })
                        : t('calendar.writeToNone')}
                </p>
            </CardContent>
            <AppointmentDialog
                event={open}
                day={today}
                onClose={() => setOpen(null)}
            />
        </Card>
    );
}

/** A short line under a row that needs attention. */
function RowNote({ event }: { event: CalendarEvent }) {
    const { t } = useTranslation();

    if (event.conflict) {
        return (
            <span className="inline-flex items-center gap-1 text-sm text-reos-tone-info-fg">
                <RefreshCw aria-hidden className="size-4" />
                {event.conflict.kept === 'tool'
                    ? t('calendar.conflict.keptTool', { tool: event.tool })
                    : t('calendar.conflict.keptReos', { tool: event.tool })}
            </span>
        );
    }

    if (event.sync === 'failed' || event.sync === 'pending') {
        return (
            <span
                className={cn(
                    'inline-flex items-center gap-1 text-sm',
                    event.sync === 'failed'
                        ? 'text-reos-tone-danger-fg'
                        : 'text-muted-foreground',
                )}
            >
                {event.sync === 'failed' ? (
                    <TriangleAlert aria-hidden className="size-4" />
                ) : null}
                {t(`calendar.sync.${event.sync}`, { tool: event.tool })}
            </span>
        );
    }

    return null;
}
