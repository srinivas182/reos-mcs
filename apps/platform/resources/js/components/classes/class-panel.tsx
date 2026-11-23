import { useEcho } from '@laravel/echo-react';
import {
    BarChart3,
    Check,
    Hand,
    MessageCircleQuestion,
    MessagesSquare,
    ThumbsUp,
    UserCheck,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ClassLive, ClassPoll } from '@/components/classes/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { getJson, postJson } from '@/lib/content';
import { realtimeEnabled } from '@/lib/realtime';
import { cn } from '@/lib/utils';
import {
    checks as checksRoute,
    hand as handRoute,
    live as liveRoute,
    messages as messagesRoute,
    polls as pollsRoute,
    questions as questionsRoute,
    stage as stageRoute,
} from '@/routes/classes';
import { answer as answerCheckRoute } from '@/routes/classes/checks';
import {
    close as closePollRoute,
    vote as voteRoute,
} from '@/routes/classes/polls';
import {
    answered as answeredRoute,
    like as likeRoute,
} from '@/routes/classes/questions';

type Tab = 'questions' | 'poll' | 'chat' | 'hands' | 'attendance';

/** What the panel asks the server to do; each returns the class's new state. */
export type ClassActions = {
    hand: (raised: boolean) => void;
    stage: (user: number, on: boolean) => void;
    poll: (question: string, options: string[]) => void;
    closePoll: (poll: number) => void;
    vote: (poll: number, option: number) => void;
    ask: (body: string) => void;
    like: (question: number, like: boolean) => void;
    answered: (question: number, answered: boolean) => void;
    chat: (body: string) => void;
    sendCheck: () => void;
    answerCheck: (check: number) => void;
};

const time = (iso: string, language: string) =>
    new Date(iso).toLocaleTimeString(language, {
        hour: 'numeric',
        minute: '2-digit',
    });

function PollResults({ poll }: { poll: ClassPoll }) {
    const { t } = useTranslation();
    const total = poll.total ?? 0;

    return (
        <ul className="space-y-2">
            {poll.options.map((option, i) => {
                const votes = option.votes ?? 0;
                const share = total > 0 ? Math.round((votes / total) * 100) : 0;

                return (
                    <li key={option.label} className="space-y-1">
                        <p className="flex justify-between gap-3 text-base">
                            <span>
                                {option.label}
                                {poll.my_vote === i ? (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {t('classes.poll.yours')}
                                    </span>
                                ) : null}
                            </span>
                            <span className="tabular-nums">
                                {t('classes.poll.share', { share, votes })}
                            </span>
                        </p>
                        <div
                            aria-hidden
                            className="h-2 overflow-hidden rounded-reos-pill bg-muted"
                        >
                            <div
                                className="h-full bg-reos-action"
                                style={{ width: `${share}%` }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

function NewPoll({ onLaunch }: { onLaunch: ClassActions['poll'] }) {
    const { t } = useTranslation();
    const [question, setQuestion] = useState('');
    const [options, setOptions] = useState(['', '']);
    const filled = options.map((o) => o.trim()).filter(Boolean);

    return (
        <form
            className="space-y-3 rounded-reos-md border border-border p-3"
            onSubmit={(e) => {
                e.preventDefault();
                onLaunch(question.trim(), filled);
                setQuestion('');
                setOptions(['', '']);
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="poll-question">
                    {t('classes.poll.question')}
                </Label>
                <Input
                    id="poll-question"
                    value={question}
                    maxLength={200}
                    onChange={(e) => setQuestion(e.target.value)}
                />
            </div>
            {options.map((option, i) => (
                <div key={i} className="grid gap-2">
                    <Label htmlFor={`poll-option-${i}`}>
                        {t('classes.poll.option', { number: i + 1 })}
                    </Label>
                    <Input
                        id={`poll-option-${i}`}
                        value={option}
                        maxLength={80}
                        onChange={(e) =>
                            setOptions(
                                options.map((o, j) =>
                                    j === i ? e.target.value : o,
                                ),
                            )
                        }
                    />
                </div>
            ))}
            <div className="flex flex-wrap gap-2">
                {options.length < 5 ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOptions([...options, ''])}
                    >
                        {t('classes.poll.addOption')}
                    </Button>
                ) : null}
                <Button
                    type="submit"
                    disabled={question.trim().length < 3 || filled.length < 2}
                >
                    {t('classes.poll.launch')}
                </Button>
            </div>
        </form>
    );
}

/**
 * The class tools beside the video (A10.2b; candidate L12, instructor
 * i12): an attendance check to answer, Raise hand, questions with likes,
 * the poll and chat for students; for the host also raised hands (Bring
 * on) and attendance checks with their answers.
 */
export function ClassPanel({
    live,
    actions,
    error,
}: {
    live: ClassLive;
    actions: ClassActions;
    error?: string | null;
}) {
    const { t, i18n } = useTranslation();
    const host = live.me.role === 'host';
    const [tab, setTab] = useState<Tab>('questions');
    const [question, setQuestion] = useState('');
    const [message, setMessage] = useState('');
    const poll = live.polls[0] ?? null;
    const tabs: { value: Tab; label: string; icon: typeof Hand }[] = [
        {
            value: 'questions',
            label: t('classes.tabs.questions'),
            icon: MessageCircleQuestion,
        },
        { value: 'poll', label: t('classes.tabs.poll'), icon: BarChart3 },
        { value: 'chat', label: t('classes.tabs.chat'), icon: MessagesSquare },
        ...(host
            ? [
                  {
                      value: 'hands' as const,
                      label: t('classes.tabs.hands', {
                          number: live.hands.filter((h) => !h.on_stage).length,
                      }),
                      icon: Hand,
                  },
                  {
                      value: 'attendance' as const,
                      label: t('classes.tabs.attendance'),
                      icon: UserCheck,
                  },
              ]
            : []),
    ];

    return (
        <aside
            aria-label={t('classes.panel')}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-base text-muted-foreground">
                    {t('classes.viewers', { count: live.viewers })}
                </p>
                {!host ? (
                    <Button
                        variant={live.me.hand ? 'default' : 'outline'}
                        aria-pressed={live.me.hand}
                        onClick={() => actions.hand(!live.me.hand)}
                    >
                        <Hand aria-hidden />
                        {live.me.hand
                            ? t('classes.lowerHand')
                            : t('classes.raiseHand')}
                    </Button>
                ) : null}
            </div>

            <div aria-live="assertive">
                {live.check && !live.check.answered ? (
                    <div
                        role="alert"
                        className="space-y-3 rounded-reos-md border border-reos-tone-warning-fg bg-reos-tone-warning-bg p-4 text-reos-tone-warning-fg"
                    >
                        <p className="text-base font-semibold">
                            {t('classes.check.title')}
                        </p>
                        <p className="text-base">
                            {t('classes.check.body', {
                                time: time(live.check.closes_at, i18n.language),
                            })}
                        </p>
                        <Button
                            onClick={() =>
                                live.check
                                    ? actions.answerCheck(live.check.id)
                                    : undefined
                            }
                        >
                            <Check aria-hidden />
                            {t('classes.check.here')}
                        </Button>
                    </div>
                ) : live.check?.answered ? (
                    <p className="text-base text-reos-tone-success-fg">
                        {t('classes.check.answered')}
                    </p>
                ) : null}
                {live.me.on_stage ? (
                    <p className="text-base font-semibold">
                        {t('classes.onStage')}
                    </p>
                ) : null}
            </div>

            <ToggleGroup
                type="single"
                variant="outline"
                value={tab}
                aria-label={t('classes.panel')}
                className="flex-wrap justify-start"
                onValueChange={(next) =>
                    next ? setTab(next as Tab) : undefined
                }
            >
                {tabs.map(({ value, label, icon: Icon }) => (
                    <ToggleGroupItem
                        key={value}
                        value={value}
                        className="min-h-12 px-3"
                    >
                        <Icon aria-hidden />
                        {label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {error ? <InputError message={error} /> : null}

            {tab === 'questions' ? (
                <section className="space-y-3">
                    {!host ? (
                        <form
                            className="space-y-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                actions.ask(question.trim());
                                setQuestion('');
                            }}
                        >
                            <Label htmlFor="class-question">
                                {t('classes.ask.label')}
                            </Label>
                            <Textarea
                                id="class-question"
                                rows={2}
                                maxLength={500}
                                value={question}
                                onChange={(e) => setQuestion(e.target.value)}
                            />
                            <Button
                                type="submit"
                                disabled={question.trim().length < 3}
                            >
                                {t('classes.ask.send')}
                            </Button>
                        </form>
                    ) : null}
                    {live.questions.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {host
                                ? t('classes.ask.emptyHost')
                                : t('classes.ask.empty')}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {live.questions.map((q) => (
                                <li
                                    key={q.id}
                                    className={cn(
                                        'space-y-2 rounded-reos-md border border-border p-3',
                                        q.answered && 'opacity-70',
                                    )}
                                >
                                    <p className="text-base">{q.body}</p>
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                                        <span>
                                            {q.mine
                                                ? t('classes.ask.you')
                                                : q.by}
                                            {q.answered
                                                ? ` · ${t('classes.ask.answered')}`
                                                : ''}
                                        </span>
                                        {host ? (
                                            <span className="flex items-center gap-2">
                                                <span>
                                                    {t('classes.ask.likes', {
                                                        count: q.likes,
                                                    })}
                                                </span>
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        actions.answered(
                                                            q.id,
                                                            !q.answered,
                                                        )
                                                    }
                                                >
                                                    {q.answered
                                                        ? t(
                                                              'classes.ask.reopen',
                                                          )
                                                        : t(
                                                              'classes.ask.markAnswered',
                                                          )}
                                                </Button>
                                            </span>
                                        ) : (
                                            <Button
                                                variant="ghost"
                                                aria-pressed={q.liked}
                                                disabled={q.mine}
                                                onClick={() =>
                                                    actions.like(q.id, !q.liked)
                                                }
                                            >
                                                <ThumbsUp
                                                    aria-hidden
                                                    className={cn(
                                                        q.liked &&
                                                            'fill-current',
                                                    )}
                                                />
                                                <span className="sr-only">
                                                    {t('classes.ask.likeSr')}
                                                </span>
                                                {q.likes}
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ) : null}

            {tab === 'poll' ? (
                <section className="space-y-3">
                    {host ? <NewPoll onLaunch={actions.poll} /> : null}
                    {poll === null ? (
                        <p className="text-base text-muted-foreground">
                            {t('classes.poll.none')}
                        </p>
                    ) : (
                        <div className="space-y-3">
                            <h3 className="text-base font-semibold">
                                {poll.question}
                                {poll.closed
                                    ? ` · ${t('classes.poll.closed')}`
                                    : ''}
                            </h3>
                            {poll.total === null ? (
                                <ul className="grid gap-2">
                                    {poll.options.map((option, i) => (
                                        <li key={option.label}>
                                            <Button
                                                variant="outline"
                                                className="w-full justify-start"
                                                onClick={() =>
                                                    actions.vote(poll.id, i)
                                                }
                                            >
                                                {option.label}
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <PollResults poll={poll} />
                            )}
                            {host && !poll.closed ? (
                                <Button
                                    variant="outline"
                                    onClick={() => actions.closePoll(poll.id)}
                                >
                                    {t('classes.poll.close')}
                                </Button>
                            ) : null}
                        </div>
                    )}
                </section>
            ) : null}

            {tab === 'chat' ? (
                <section className="space-y-3">
                    <ul
                        aria-live="polite"
                        className="max-h-80 space-y-2 overflow-y-auto"
                    >
                        {live.messages.length === 0 ? (
                            <li className="text-base text-muted-foreground">
                                {t('classes.chat.empty')}
                            </li>
                        ) : (
                            live.messages.map((m) => (
                                <li key={m.id} className="text-base">
                                    <span className="font-semibold">
                                        {m.mine ? t('classes.ask.you') : m.by}
                                    </span>{' '}
                                    <span className="text-sm text-muted-foreground">
                                        {time(m.at, i18n.language)}
                                    </span>
                                    <p>{m.body}</p>
                                </li>
                            ))
                        )}
                    </ul>
                    <form
                        className="flex gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            actions.chat(message.trim());
                            setMessage('');
                        }}
                    >
                        <Input
                            aria-label={t('classes.chat.label')}
                            placeholder={t('classes.chat.label')}
                            value={message}
                            maxLength={500}
                            onChange={(e) => setMessage(e.target.value)}
                        />
                        <Button type="submit" disabled={message.trim() === ''}>
                            {t('classes.chat.send')}
                        </Button>
                    </form>
                </section>
            ) : null}

            {tab === 'hands' && host ? (
                <section className="space-y-2">
                    {live.hands.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('classes.hands.none')}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {live.hands.map((h) => (
                                <li
                                    key={h.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-reos-md border border-border p-3"
                                >
                                    <span className="text-base">
                                        {h.name}
                                        {h.on_stage
                                            ? ` · ${t('classes.hands.speaking')}`
                                            : ''}
                                    </span>
                                    <Button
                                        variant={
                                            h.on_stage ? 'outline' : 'default'
                                        }
                                        onClick={() =>
                                            actions.stage(h.id, !h.on_stage)
                                        }
                                    >
                                        {h.on_stage
                                            ? t('classes.hands.takeOff')
                                            : t('classes.hands.bringOn')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ) : null}

            {tab === 'attendance' && host ? (
                <section className="space-y-3">
                    <p className="text-base text-muted-foreground">
                        {live.ce
                            ? live.next_check_at
                                ? t('classes.attendance.next', {
                                      time: time(
                                          live.next_check_at,
                                          i18n.language,
                                      ),
                                  })
                                : t('classes.attendance.noMore')
                            : t('classes.attendance.notCe')}
                    </p>
                    <Button variant="outline" onClick={actions.sendCheck}>
                        <UserCheck aria-hidden />
                        {t('classes.attendance.send')}
                    </Button>
                    {live.checks.length > 0 ? (
                        <ul className="space-y-1">
                            {live.checks.map((c) => (
                                <li key={c.id} className="text-base">
                                    {t('classes.attendance.row', {
                                        time: time(c.opens_at, i18n.language),
                                        answered: c.answered,
                                        of: c.of,
                                    })}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </section>
            ) : null}
        </aside>
    );
}

/** The class's state, fetched now, on `class.changed`, and every 15 seconds. */
export function useClassLive(sessionId: string) {
    const [live, setLive] = useState<ClassLive | null>(null);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(() => {
        void getJson<{ data: ClassLive }>(liveRoute(sessionId).url)
            .then((r) => setLive(r.data))
            .catch(() => undefined);
    }, [sessionId]);

    useEffect(() => {
        load();
        const timer = setInterval(load, 15_000);

        return () => clearInterval(timer);
    }, [load]);

    const run = useCallback((url: string, body: unknown = {}) => {
        void postJson<{ data: ClassLive }>(url, body)
            .then((r) => {
                setLive(r.data);
                setError(null);
            })
            .catch((e: unknown) =>
                setError(e instanceof Error ? e.message : String(e)),
            );
    }, []);

    const s = sessionId;
    const actions: ClassActions = {
        hand: (raised) => run(handRoute(s).url, { raised }),
        stage: (user, on) => run(stageRoute(s).url, { user, on }),
        poll: (question, options) =>
            run(pollsRoute(s).url, { question, options }),
        closePoll: (poll) => run(closePollRoute({ session: s, poll }).url),
        vote: (poll, option) =>
            run(voteRoute({ session: s, poll }).url, { option }),
        ask: (body) => run(questionsRoute(s).url, { body }),
        like: (question, like) =>
            run(likeRoute({ session: s, question }).url, { like }),
        answered: (question, answered) =>
            run(answeredRoute({ session: s, question }).url, { answered }),
        chat: (body) => run(messagesRoute(s).url, { body }),
        sendCheck: () => run(checksRoute(s).url),
        answerCheck: (check) =>
            run(answerCheckRoute({ session: s, check }).url),
    };

    return { live, error, reload: load, actions };
}

function ClassEcho({
    me,
    sessionId,
    onChanged,
}: {
    me: number;
    sessionId: string;
    onChanged: () => void;
}) {
    useEcho<{ session: string }>(
        `users.${me}`,
        '.class.changed',
        (event) => {
            if (event.session === sessionId) {
                onChanged();
            }
        },
        [sessionId],
    );

    return null;
}

/** The panel with its data, for the room. */
export function LiveClassPanel({
    sessionId,
    me,
}: {
    sessionId: string;
    me: number;
}) {
    const { live, error, reload, actions } = useClassLive(sessionId);

    return (
        <>
            {realtimeEnabled() && me > 0 ? (
                <ClassEcho me={me} sessionId={sessionId} onChanged={reload} />
            ) : null}
            {live ? (
                <ClassPanel live={live} actions={actions} error={error} />
            ) : null}
        </>
    );
}
