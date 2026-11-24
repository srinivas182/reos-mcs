import {
    CalendarPlus,
    Image as ImageIcon,
    MoveRight,
    Repeat2,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** The content calendar (B12.3), as the API and page send it. */
export type PostStatus =
    | 'draft'
    | 'approval'
    | 'scheduled'
    | 'publishing'
    | 'published'
    | 'partly'
    | 'failed';

export type CalendarPost = {
    id: string;
    status: PostStatus;
    body: string;
    time: string | null;
    channels: string[];
    thumb: string | null;
    kind: string | null;
    movable: boolean;
};

export type CalendarDay = {
    date: string;
    today: boolean;
    posts: CalendarPost[];
};

export type Calendar = {
    view: 'week' | 'month';
    date: string;
    from: string;
    to: string;
    previous: string;
    next: string;
    timezone: string;
    days: CalendarDay[];
    scheduled: number;
};

const tone = (status: PostStatus) =>
    status === 'scheduled'
        ? 'bg-reos-tone-info-bg text-reos-tone-info-fg'
        : status === 'published'
          ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
          : status === 'failed' || status === 'partly'
            ? 'bg-reos-tone-danger-bg text-reos-tone-danger-fg'
            : 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg';

/**
 * A week or a month of posts (B12.3; web screen 17's calendar). Drag a
 * post to another day, or use Move with a date; Reuse plans a copy of any
 * post for a day; Plan a post opens the composer for that day.
 */
export function ContentCalendar({
    calendar,
    onMove,
    onReuse,
    onPlan,
    busy = false,
}: {
    calendar: Calendar;
    onMove: (post: CalendarPost, date: string) => void;
    onReuse: (post: CalendarPost, date: string) => void;
    onPlan: (date: string) => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const [over, setOver] = useState<string | null>(null);
    const [open, setOpen] = useState<{
        id: string;
        action: 'move' | 'reuse';
    } | null>(null);
    const [target, setTarget] = useState('');
    const dayName = (date: string, style: 'long' | 'short') =>
        new Intl.DateTimeFormat(i18n.language, {
            weekday: style,
            month: style === 'long' ? 'long' : 'short',
            day: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${date}T00:00:00Z`));
    const empty = calendar.days.every((d) => d.posts.length === 0);
    const week = calendar.view === 'week';

    return (
        <div className="space-y-4">
            <p className="text-base text-muted-foreground">
                {t('social.calendar.drag')}
            </p>
            {empty ? (
                <EmptyState
                    icon={CalendarPlus}
                    title={t('social.calendar.empty')}
                    description={t('social.calendar.emptyHint')}
                />
            ) : null}
            <ol
                className={cn(
                    'grid gap-3',
                    week
                        ? 'md:grid-cols-7'
                        : 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7',
                )}
            >
                {calendar.days.map((day) => (
                    <li
                        key={day.date}
                        aria-label={dayName(day.date, 'long')}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setOver(day.date);
                        }}
                        onDragLeave={() => setOver(null)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setOver(null);
                            const id = e.dataTransfer.getData('text/plain');
                            const post = calendar.days
                                .flatMap((d) => d.posts)
                                .find((p) => p.id === id);
                            if (post?.movable) onMove(post, day.date);
                        }}
                        className={cn(
                            'flex min-h-40 flex-col gap-2 rounded-reos-lg border border-border bg-card p-3',
                            day.today && 'border-reos-action',
                            over === day.date && 'bg-reos-tone-info-bg',
                        )}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p
                                className={cn(
                                    'text-base font-semibold',
                                    day.today && 'text-reos-action',
                                )}
                            >
                                {dayName(day.date, week ? 'long' : 'short')}
                            </p>
                            {day.today ? (
                                <Badge variant="outline">
                                    {t('social.calendar.today')}
                                </Badge>
                            ) : null}
                        </div>
                        <ul className="flex flex-1 flex-col gap-2">
                            {day.posts.map((post) => (
                                <li
                                    key={post.id}
                                    draggable={post.movable}
                                    onDragStart={(e) =>
                                        e.dataTransfer.setData(
                                            'text/plain',
                                            post.id,
                                        )
                                    }
                                    className="space-y-2 rounded-reos-md border border-border bg-background p-2"
                                >
                                    <div className="flex items-center gap-2">
                                        {post.thumb && post.kind === 'image' ? (
                                            <img
                                                src={post.thumb}
                                                alt=""
                                                className="size-10 rounded-reos-sm object-cover"
                                            />
                                        ) : post.thumb ? (
                                            <ImageIcon
                                                aria-hidden
                                                className="size-5 text-muted-foreground"
                                            />
                                        ) : null}
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-sm font-medium',
                                                tone(post.status),
                                            )}
                                        >
                                            {t(
                                                `social.calendar.status.${post.status}`,
                                            )}
                                        </span>
                                        {post.time ? (
                                            <span className="text-sm text-muted-foreground">
                                                {post.time}
                                            </span>
                                        ) : null}
                                    </div>
                                    <p className="line-clamp-3 text-base">
                                        {post.body}
                                    </p>
                                    {open?.id === post.id ? (
                                        <form
                                            className="space-y-2"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                if (target === '') return;
                                                if (open.action === 'move')
                                                    onMove(post, target);
                                                else onReuse(post, target);
                                                setOpen(null);
                                            }}
                                        >
                                            <Label htmlFor={`date-${post.id}`}>
                                                {open.action === 'move'
                                                    ? t(
                                                          'social.calendar.moveTo',
                                                      )
                                                    : t(
                                                          'social.calendar.reuseOn',
                                                      )}
                                            </Label>
                                            <Input
                                                id={`date-${post.id}`}
                                                type="date"
                                                value={target}
                                                onChange={(e) =>
                                                    setTarget(e.target.value)
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                className="w-full"
                                                disabled={busy || target === ''}
                                            >
                                                {open.action === 'move'
                                                    ? t('social.calendar.move')
                                                    : t(
                                                          'social.calendar.reuse',
                                                      )}
                                            </Button>
                                        </form>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {post.movable ? (
                                                <Button
                                                    variant="outline"
                                                    disabled={busy}
                                                    onClick={() => {
                                                        setTarget(day.date);
                                                        setOpen({
                                                            id: post.id,
                                                            action: 'move',
                                                        });
                                                    }}
                                                >
                                                    <MoveRight aria-hidden />
                                                    {t('social.calendar.move')}
                                                </Button>
                                            ) : null}
                                            <Button
                                                variant="outline"
                                                disabled={busy}
                                                onClick={() => {
                                                    setTarget('');
                                                    setOpen({
                                                        id: post.id,
                                                        action: 'reuse',
                                                    });
                                                }}
                                            >
                                                <Repeat2 aria-hidden />
                                                {t('social.calendar.reuse')}
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() => onPlan(day.date)}
                        >
                            <CalendarPlus aria-hidden />
                            {t('social.calendar.plan')}
                        </Button>
                    </li>
                ))}
            </ol>
        </div>
    );
}
