import {
    ClipboardCheck,
    ExternalLink,
    Inbox,
    TriangleAlert,
} from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson, putJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type { ApprovalItem, ApprovalKind, ApprovalsQueueData } from './types';

type Filter = 'all' | ApprovalKind;

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';

const text = (v: unknown): string | null =>
    typeof v === 'string' && v !== '' ? v : null;

/** The advertising checks on a post: the words, and the fix for each. */
const issueList = (v: unknown): string[] =>
    Array.isArray(v)
        ? v
              .map((i) => {
                  if (typeof i !== 'object' || i === null) {
                      return '';
                  }

                  const issue = i as { phrase?: unknown; fix?: unknown };

                  return [
                      text(issue.phrase) ? `"${text(issue.phrase)}"` : null,
                      text(issue.fix),
                  ]
                      .filter(Boolean)
                      .join(': ');
              })
              .filter((i) => i !== '')
        : [];

const REASONS = ['unclear', 'money', 'legal', 'asked'] as const;

/** Why the virtual broker sent the question on. */
const reasonOf = (v: unknown): (typeof REASONS)[number] =>
    REASONS.find((r) => r === v) ?? 'asked';

function errorOf(e: unknown): Record<string, string> {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        const errors = (e as { errors: Record<string, string[] | string> })
            .errors;

        return Object.fromEntries(
            Object.entries(errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? (v[0] ?? '') : v,
            ]),
        );
    }

    return { text: e instanceof Error ? e.message : String(e) };
}

/**
 * One approvals queue (A15.3, broker journey screens 7–12): the waiting
 * items, oldest first, beside the one being reviewed. Posts and pages are
 * approved or sent back with a note; questions are answered, and the
 * answer can teach the virtual broker.
 */
export function ApprovalsQueue({
    queue: initial,
    routes,
}: {
    queue: ApprovalsQueueData;
    routes: {
        act: (kind: ApprovalKind, id: string) => string;
        settings: string;
    };
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [queue, setQueue] = useState(initial);
    const [filter, setFilter] = useState<Filter>('all');
    const [selected, setSelected] = useState<string | null>(
        initial.items[0]
            ? `${initial.items[0].kind}:${initial.items[0].id}`
            : null,
    );
    const [note, setNote] = useState('');
    const [learn, setLearn] = useState(true);
    const [hours, setHours] = useState(String(initial.fallback_hours));
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const shown = queue.items.filter(
        (i) => filter === 'all' || i.kind === filter,
    );
    const current =
        queue.items.find((i) => `${i.kind}:${i.id}` === selected) ?? null;
    const filters: { key: Filter; count: number }[] = [
        { key: 'all', count: queue.items.length },
        ...(queue.can.posts
            ? [
                  { key: 'post' as const, count: queue.counts.posts },
                  { key: 'page' as const, count: queue.counts.pages },
              ]
            : []),
        ...(queue.can.questions
            ? [{ key: 'question' as const, count: queue.counts.questions }]
            : []),
    ];

    const choose = (item: ApprovalItem | null) => {
        setSelected(item ? `${item.kind}:${item.id}` : null);
        setNote('');
        setErrors({});
    };

    const act = async (
        item: ApprovalItem,
        action: 'approve' | 'return' | 'answer',
    ) => {
        setBusy(true);
        setErrors({});

        try {
            const res = await postJson<{ data: ApprovalsQueueData }>(
                routes.act(item.kind, item.id),
                {
                    action,
                    text: action === 'approve' ? null : note,
                    add_to_knowledge: action === 'answer' ? learn : false,
                },
            );
            setQueue(res.data);
            const next =
                res.data.items.find(
                    (i) => filter === 'all' || i.kind === filter,
                ) ?? null;
            choose(next);
            toast.success(
                action === 'approve'
                    ? t('brokerage.approvals.approved')
                    : t(
                          action === 'return'
                              ? 'brokerage.approvals.returned'
                              : 'brokerage.approvals.answered',
                          { agent: item.agent },
                      ),
            );
        } catch (e) {
            setErrors(errorOf(e));
        } finally {
            setBusy(false);
        }
    };

    const waited = (item: ApprovalItem) =>
        item.hours < 0.1
            ? t('brokerage.approvals.waitedNew')
            : t('brokerage.approvals.waited', {
                  hours: item.hours.toLocaleString(i18n.language, {
                      maximumFractionDigits: 1,
                  }),
              });

    return (
        <div className="space-y-6">
            <p className="flex flex-wrap items-center gap-3 text-base text-muted-foreground">
                <span>{t('brokerage.approvals.counts', queue.counts)}</span>
                {queue.counts.overdue > 0 ? (
                    <span className="inline-flex items-center gap-1 rounded-full bg-reos-tone-warning-bg px-3 py-1 text-reos-tone-warning-fg">
                        <TriangleAlert aria-hidden className="size-4" />
                        {t('brokerage.approvals.overdueCount', {
                            count: queue.counts.overdue,
                            hours: queue.fallback_hours,
                        })}
                    </span>
                ) : null}
            </p>

            <div
                role="group"
                aria-label={t('brokerage.approvals.filterLabel')}
                className="flex flex-wrap gap-2"
            >
                {filters.map((f) => (
                    <Button
                        key={f.key}
                        type="button"
                        variant={filter === f.key ? 'default' : 'outline'}
                        aria-pressed={filter === f.key}
                        onClick={() => setFilter(f.key)}
                    >
                        {t(`brokerage.approvals.filters.${f.key}`)} ({f.count})
                    </Button>
                ))}
            </div>

            {shown.length === 0 ? (
                <EmptyState
                    icon={Inbox}
                    title={t('brokerage.approvals.emptyTitle')}
                    description={t('brokerage.approvals.empty')}
                />
            ) : (
                <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                    <ul
                        aria-label={t('brokerage.approvals.title')}
                        className="space-y-2"
                    >
                        {shown.map((item) => {
                            const key = `${item.kind}:${item.id}`;

                            return (
                                <li key={key}>
                                    <button
                                        type="button"
                                        onClick={() => choose(item)}
                                        aria-current={
                                            selected === key
                                                ? 'true'
                                                : undefined
                                        }
                                        className={cn(
                                            'flex min-h-12 w-full flex-col gap-1 rounded-reos-md border bg-card p-4 text-left',
                                            selected === key
                                                ? 'border-primary ring-2 ring-primary/30'
                                                : 'border-border',
                                        )}
                                    >
                                        <span className="flex flex-wrap items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {t(
                                                    `brokerage.approvals.kinds.${item.kind}`,
                                                )}{' '}
                                                · {item.agent}
                                            </span>
                                            {item.overdue ? (
                                                <span className="rounded-full bg-reos-tone-warning-bg px-2 py-0.5 text-sm text-reos-tone-warning-fg">
                                                    {t(
                                                        'brokerage.approvals.overdue',
                                                    )}
                                                </span>
                                            ) : null}
                                        </span>
                                        <span className="line-clamp-2 text-base">
                                            {item.summary}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {waited(item)}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>

                    {current === null ? (
                        <EmptyState
                            icon={ClipboardCheck}
                            title={t('brokerage.approvals.chooseTitle')}
                            description={t('brokerage.approvals.choose')}
                        />
                    ) : (
                        <section
                            aria-labelledby={`${ids}-item`}
                            className={card}
                        >
                            <header className="space-y-1">
                                <h2
                                    id={`${ids}-item`}
                                    className="font-semibold"
                                >
                                    {t(
                                        `brokerage.approvals.kinds.${current.kind}`,
                                    )}
                                </h2>
                                <p className="text-base text-muted-foreground">
                                    {t('brokerage.approvals.from', {
                                        agent: current.agent,
                                    })}{' '}
                                    · {waited(current)}
                                </p>
                            </header>
                            <Detail item={current} />
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-text`}>
                                    {current.kind === 'question'
                                        ? t('brokerage.approvals.answer')
                                        : t('brokerage.approvals.note')}
                                </Label>
                                <Textarea
                                    id={`${ids}-text`}
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    maxLength={4000}
                                    rows={4}
                                    aria-describedby={`${ids}-hint`}
                                />
                                <p
                                    id={`${ids}-hint`}
                                    className="text-sm text-muted-foreground"
                                >
                                    {current.kind === 'question'
                                        ? t('brokerage.approvals.answerHint')
                                        : t('brokerage.approvals.noteHint')}
                                </p>
                                <InputError
                                    message={errors.text ?? errors.action}
                                />
                            </div>
                            {current.kind === 'question' ? (
                                <>
                                    <label className="flex min-h-12 items-center gap-3 text-base">
                                        <Checkbox
                                            checked={learn}
                                            onCheckedChange={(v) =>
                                                setLearn(v === true)
                                            }
                                        />
                                        {t(
                                            'brokerage.approvals.addToKnowledge',
                                        )}
                                    </label>
                                    <Button
                                        type="button"
                                        disabled={busy}
                                        onClick={() =>
                                            void act(current, 'answer')
                                        }
                                    >
                                        {t('brokerage.approvals.send')}
                                    </Button>
                                </>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        disabled={busy}
                                        onClick={() =>
                                            void act(current, 'approve')
                                        }
                                    >
                                        {t('brokerage.approvals.approve')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busy}
                                        onClick={() =>
                                            void act(current, 'return')
                                        }
                                    >
                                        {t('brokerage.approvals.return')}
                                    </Button>
                                </div>
                            )}
                        </section>
                    )}
                </div>
            )}

            {queue.can.settings ? (
                <section aria-labelledby={`${ids}-settings`} className={card}>
                    <h2 id={`${ids}-settings`} className="font-semibold">
                        {t('brokerage.approvals.settings.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.approvals.settings.purpose')}
                    </p>
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setBusy(true);
                            putJson<{ data: ApprovalsQueueData }>(
                                routes.settings,
                                { fallback_hours: Number(hours) },
                            )
                                .then((res) => {
                                    setQueue(res.data);
                                    toast.success(
                                        t(
                                            'brokerage.approvals.settings.saved',
                                            { count: res.data.fallback_hours },
                                        ),
                                    );
                                })
                                .catch((err: unknown) =>
                                    setErrors(errorOf(err)),
                                )
                                .finally(() => setBusy(false));
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor={`${ids}-hours`}>
                                {t('brokerage.approvals.settings.hours')}
                            </Label>
                            <Input
                                id={`${ids}-hours`}
                                type="number"
                                min={1}
                                max={40}
                                value={hours}
                                onChange={(e) => setHours(e.target.value)}
                                className="w-32"
                                aria-describedby={`${ids}-hours-hint`}
                            />
                        </div>
                        <Button type="submit" variant="outline" disabled={busy}>
                            {t('brokerage.approvals.settings.save')}
                        </Button>
                        <p
                            id={`${ids}-hours-hint`}
                            className="w-full text-sm text-muted-foreground"
                        >
                            {t('brokerage.approvals.settings.hoursHint')}
                        </p>
                        <InputError message={errors.fallback_hours} />
                    </form>
                </section>
            ) : null}
        </div>
    );
}

/** What the broker needs to decide: the post with its checks, the page, or the question with the AI's answer. */
function Detail({ item }: { item: ApprovalItem }) {
    const { t, i18n } = useTranslation();
    const d = item.detail;

    if (item.kind === 'post') {
        const issues = issueList(d.issues);
        const channels = Array.isArray(d.channels)
            ? d.channels.map(String)
            : [];
        const scheduled = text(d.scheduled_at);

        return (
            <div className="space-y-3">
                <p className="text-base whitespace-pre-line">{text(d.body)}</p>
                {text(d.attribution) ? (
                    <p className="text-sm text-muted-foreground">
                        {text(d.attribution)}
                    </p>
                ) : null}
                {channels.length > 0 ? (
                    <p className="text-base">
                        {t('brokerage.approvals.channels', {
                            channels: channels.join(', '),
                        })}
                    </p>
                ) : null}
                {scheduled ? (
                    <p className="text-base">
                        {t('brokerage.approvals.scheduled', {
                            date: new Date(scheduled).toLocaleString(
                                i18n.language,
                            ),
                        })}
                    </p>
                ) : null}
                <div className="space-y-1">
                    <h3 className="font-medium">
                        {t('brokerage.approvals.issues')}
                    </h3>
                    {issues.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('brokerage.approvals.noIssues')}
                        </p>
                    ) : (
                        <ul className="list-disc space-y-1 pl-5 text-base">
                            {issues.map((i) => (
                                <li key={i}>{i}</li>
                            ))}
                        </ul>
                    )}
                </div>
                {text(d.reason) ? (
                    <p className="text-base">
                        {t('brokerage.approvals.agentReason', {
                            agent: item.agent,
                            reason: text(d.reason),
                        })}
                    </p>
                ) : null}
            </div>
        );
    }

    if (item.kind === 'page') {
        return (
            <div className="space-y-3">
                <p className="text-base whitespace-pre-line">{text(d.bio)}</p>
                {text(d.url) ? (
                    <a
                        href={text(d.url) ?? '#'}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex min-h-12 items-center gap-1 text-base underline"
                    >
                        {t('brokerage.approvals.openPage')}
                        <ExternalLink aria-hidden className="size-4" />
                    </a>
                ) : null}
            </div>
        );
    }

    const deal =
        typeof d.deal === 'object' && d.deal !== null
            ? text((d.deal as { property?: unknown }).property)
            : null;

    return (
        <div className="space-y-3">
            <p className="text-base font-medium whitespace-pre-line">
                {text(d.question)}
            </p>
            {deal ? (
                <p className="text-base">
                    {t('brokerage.approvals.deal', { property: deal })}
                </p>
            ) : null}
            {text(d.reason) ? (
                <p className="text-base">
                    {t('brokerage.approvals.reason')}:{' '}
                    {t(`virtualBroker.reasons.${reasonOf(d.reason)}`)}
                </p>
            ) : null}
            {text(d.answer) ? (
                <div className="space-y-1 rounded-reos-md bg-muted p-3">
                    <h3 className="font-medium">
                        {t('brokerage.approvals.aiAnswer')}
                    </h3>
                    <p className="text-base whitespace-pre-line">
                        {text(d.answer)}
                    </p>
                </div>
            ) : null}
        </div>
    );
}
