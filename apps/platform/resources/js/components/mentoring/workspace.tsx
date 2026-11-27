import { Link } from '@inertiajs/react';
import {
    CalendarClock,
    ClipboardCheck,
    NotebookPen,
    Star,
    Target,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    MenteeDetailData,
    MenteeRow,
    MentorTodayData,
    ReviewRow,
    Signal,
} from './types';

const tone: Record<Signal, string> = {
    quiet: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    behind: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    first_session: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    ok: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

const when = (iso: string, lang: string) =>
    new Intl.DateTimeFormat(lang, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));

/** A mentee in a list: name, signal, assigned, next session. */
export function MenteeItem({
    mentee,
    href,
}: {
    mentee: MenteeRow;
    href: string;
}) {
    const { t, i18n } = useTranslation();

    return (
        <li>
            <Link
                href={href}
                className="flex min-h-12 flex-wrap items-center justify-between gap-2 rounded-reos-md border border-border bg-card p-3 hover:bg-muted"
            >
                <span className="flex items-center gap-2 text-base font-medium">
                    <UserRound
                        aria-hidden
                        className="size-5 text-muted-foreground"
                    />
                    {mentee.name}
                </span>
                <span className="flex flex-wrap items-center gap-2 text-sm">
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 font-medium',
                            tone[mentee.signal],
                        )}
                    >
                        {t(`mentoring.workspace.signals.${mentee.signal}`)}
                    </span>
                    {mentee.assigned ? (
                        <Badge variant="outline">
                            {t('mentoring.workspace.assigned')}
                        </Badge>
                    ) : null}
                    {mentee.next_session ? (
                        <span className="text-muted-foreground">
                            {t('mentoring.workspace.nextSession', {
                                time: when(mentee.next_session, i18n.language),
                            })}
                        </span>
                    ) : null}
                </span>
            </Link>
        </li>
    );
}

/** Today for mentors (screen 22): who needs you, upcoming sessions, wrap-ups to send. */
export function MentorTodayView({
    today,
    mentees,
    menteeHref,
    wrapUpHref,
}: {
    today: MentorTodayData;
    mentees: MenteeRow[];
    menteeHref: (id: number) => string;
    wrapUpHref?: (uuid: string) => string;
}) {
    const { t, i18n } = useTranslation();

    return (
        <div className="space-y-6">
            <p className="text-base font-semibold">
                {t('mentoring.workspace.menteeCount', { count: today.mentees })}{' '}
                ·{' '}
                {t('mentoring.workspace.sessionsWeek', {
                    count: today.sessions_this_week,
                })}
            </p>
            <section aria-labelledby="needs" className="space-y-2">
                <h2 id="needs" className="text-lg font-semibold">
                    {t('mentoring.workspace.needs')}
                </h2>
                {today.needs.length === 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('mentoring.workspace.noNeeds')}
                    </p>
                ) : null}
                <ul className="space-y-2">
                    {today.needs.map((m) => (
                        <MenteeItem
                            key={m.id}
                            mentee={m}
                            href={menteeHref(m.id)}
                        />
                    ))}
                </ul>
            </section>
            <div className="grid gap-6 md:grid-cols-2">
                <section aria-labelledby="upcoming" className="space-y-2">
                    <h2 id="upcoming" className="text-lg font-semibold">
                        {t('mentoring.workspace.upcoming')}
                    </h2>
                    {today.upcoming.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('mentoring.workspace.noUpcoming')}
                        </p>
                    ) : null}
                    <ul className="space-y-2">
                        {today.upcoming.map((s) => (
                            <li
                                key={s.uuid}
                                className="flex items-start gap-2 rounded-reos-md border border-border p-3"
                            >
                                <CalendarClock
                                    aria-hidden
                                    className="mt-0.5 size-5 text-muted-foreground"
                                />
                                <span className="text-base">
                                    {s.agent} ·{' '}
                                    {when(s.starts_at, i18n.language)}
                                    <span className="block text-sm text-muted-foreground">
                                        {s.topic}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
                <section aria-labelledby="wrapups" className="space-y-2">
                    <h2 id="wrapups" className="text-lg font-semibold">
                        {t('mentoring.workspace.wrapUps')}
                    </h2>
                    {today.wrap_ups.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('mentoring.workspace.noWrapUps')}
                        </p>
                    ) : null}
                    <ul className="space-y-2">
                        {today.wrap_ups.map((s) => (
                            <li
                                key={s.uuid}
                                className="flex items-start gap-2 rounded-reos-md border border-border p-3"
                            >
                                <ClipboardCheck
                                    aria-hidden
                                    className="mt-0.5 size-5 text-muted-foreground"
                                />
                                <span className="text-base">
                                    {wrapUpHref ? (
                                        <Link
                                            href={wrapUpHref(s.uuid)}
                                        >{`${t('mentoring.workspace.wrapUp')}: ${s.agent}`}</Link>
                                    ) : (
                                        `${t('mentoring.workspace.wrapUp')}: ${s.agent}`
                                    )}
                                    <span className="block text-sm text-muted-foreground">
                                        {s.topic}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
            <section aria-labelledby="mentees" className="space-y-2">
                <h2 id="mentees" className="text-lg font-semibold">
                    {t('mentoring.workspace.mentees')}
                </h2>
                {mentees.length === 0 ? (
                    <EmptyState
                        icon={UserRound}
                        title={t('mentoring.workspace.noMentees')}
                        description={t('mentoring.workspace.noMenteesHint')}
                    />
                ) : null}
                <ul className="space-y-2">
                    {mentees.map((m) => (
                        <MenteeItem
                            key={m.id}
                            mentee={m}
                            href={menteeHref(m.id)}
                        />
                    ))}
                </ul>
            </section>
        </div>
    );
}

/** Mentee detail (screen 24): plan, scorecard, deals, sessions, goals and private notes. */
export function MenteeDetailView({
    detail,
    busy = false,
    onAddNote,
    onRemoveNote,
    onAddGoal,
}: {
    detail: MenteeDetailData;
    busy?: boolean;
    onAddNote: (body: string) => void;
    onRemoveNote: (id: string) => void;
    onAddGoal: (title: string, due: string | null) => void;
}) {
    const { t, i18n } = useTranslation();
    const [note, setNote] = useState('');
    const [goal, setGoal] = useState('');
    const [due, setDue] = useState('');

    return (
        <div className="space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <section className="space-y-1 rounded-reos-lg border border-border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('mentoring.workspace.plan')}
                    </h2>
                    <p className="text-base">
                        {t('mentoring.workspace.planWeek', detail.plan)}
                    </p>
                </section>
                <section className="space-y-1 rounded-reos-lg border border-border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('mentoring.workspace.scorecard')}
                    </h2>
                    {detail.scorecard.map((s) => (
                        <p key={s.key} className="text-base">
                            {t(`mentoring.workspace.metrics.${s.key}`)}:{' '}
                            {t('mentoring.workspace.metricValue', {
                                done: s.done,
                                target: s.target,
                            })}
                        </p>
                    ))}
                </section>
                <section className="space-y-1 rounded-reos-lg border border-border bg-card p-4">
                    <h2 className="text-base font-semibold">
                        {t('mentoring.workspace.deals')}
                    </h2>
                    <p className="text-base">
                        {t('mentoring.workspace.dealsValue', detail.deals)}
                    </p>
                </section>
            </div>
            <p className="text-sm text-muted-foreground">
                {t('mentoring.workspace.privacy')}
            </p>
            <div className="grid gap-6 md:grid-cols-2">
                <section aria-labelledby="goals" className="space-y-3">
                    <h2 id="goals" className="text-lg font-semibold">
                        {t('mentoring.workspace.goals')}
                    </h2>
                    {detail.goals.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('mentoring.workspace.noGoals')}
                        </p>
                    ) : null}
                    <ul className="space-y-2">
                        {detail.goals.map((g) => (
                            <li
                                key={g.id}
                                className="flex items-center gap-2 text-base"
                            >
                                <Target
                                    aria-hidden
                                    className="size-5 text-muted-foreground"
                                />
                                <span className={cn(g.done && 'line-through')}>
                                    {g.title}
                                </span>
                                {g.due_date ? (
                                    <span className="text-sm text-muted-foreground">
                                        {g.due_date}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    <form
                        className="grid gap-2 sm:grid-cols-[1fr_auto]"
                        onSubmit={(e) => {
                            e.preventDefault();
                            onAddGoal(goal.trim(), due === '' ? null : due);
                            setGoal('');
                        }}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="goal">
                                {t('mentoring.workspace.goalTitle')}
                            </Label>
                            <Input
                                id="goal"
                                value={goal}
                                maxLength={200}
                                onChange={(e) => setGoal(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="due">
                                {t('mentoring.workspace.goalDue')}
                            </Label>
                            <Input
                                id="due"
                                type="date"
                                value={due}
                                onChange={(e) => setDue(e.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={busy || goal.trim().length < 2}
                            className="sm:col-span-2"
                        >
                            <Target aria-hidden />
                            {t('mentoring.workspace.addGoal')}
                        </Button>
                    </form>
                </section>
                <section aria-labelledby="notes" className="space-y-3">
                    <h2 id="notes" className="text-lg font-semibold">
                        {t('mentoring.workspace.notes')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('mentoring.workspace.notesHint')}
                    </p>
                    {detail.notes.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('mentoring.workspace.noNotes')}
                        </p>
                    ) : null}
                    <ul className="space-y-2">
                        {detail.notes.map((n) => (
                            <li
                                key={n.id}
                                className="flex items-start justify-between gap-2 rounded-reos-md border border-border p-3"
                            >
                                <span className="text-base whitespace-pre-line">
                                    {n.body}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        'mentoring.workspace.noteRemoved',
                                    )}
                                    disabled={busy}
                                    onClick={() => onRemoveNote(n.id)}
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                    <form
                        className="space-y-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            onAddNote(note.trim());
                            setNote('');
                        }}
                    >
                        <Label htmlFor="note">
                            {t('mentoring.workspace.note')}
                        </Label>
                        <Textarea
                            id="note"
                            value={note}
                            maxLength={2000}
                            onChange={(e) => setNote(e.target.value)}
                        />
                        <Button
                            type="submit"
                            disabled={busy || note.trim().length < 2}
                        >
                            <NotebookPen aria-hidden />
                            {t('mentoring.workspace.addNote')}
                        </Button>
                    </form>
                </section>
            </div>
            <section aria-labelledby="sessions" className="space-y-2">
                <h2 id="sessions" className="text-lg font-semibold">
                    {t('mentoring.workspace.sessions')}
                </h2>
                <ul className="space-y-2">
                    {detail.sessions.map((s) => (
                        <li
                            key={s.uuid}
                            className="rounded-reos-md border border-border p-3 text-base"
                        >
                            {s.topic} · {when(s.starts_at, i18n.language)}
                            {s.summary ? (
                                <span className="block text-sm text-muted-foreground">
                                    {s.summary}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}

/** Low ratings with a comment waiting for the brokerage (agent screen 119's review queue). */
export function ReviewQueue({
    reviews,
    busy = false,
    onDecide,
}: {
    reviews: ReviewRow[];
    busy?: boolean;
    onDecide: (id: string, keep: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2">
            <p className="text-sm text-muted-foreground">
                {t('mentoring.program.reviewsHint')}
            </p>
            {reviews.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    {t('mentoring.program.noReviews')}
                </p>
            ) : null}
            <ul className="space-y-2">
                {reviews.map((r) => (
                    <li
                        key={r.id}
                        className="flex flex-wrap items-start justify-between gap-3 rounded-reos-md border border-border bg-card p-3"
                    >
                        <div className="space-y-1">
                            <p className="flex items-center gap-1 text-base font-medium">
                                <Star aria-hidden className="size-4" />
                                {r.stars} · {r.mentor} · {r.agent}
                            </p>
                            {r.comment ? (
                                <p className="text-base">{r.comment}</p>
                            ) : null}
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => onDecide(r.id, true)}
                            >
                                {t('mentoring.program.keep')}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => onDecide(r.id, false)}
                            >
                                {t('mentoring.program.hide')}
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
