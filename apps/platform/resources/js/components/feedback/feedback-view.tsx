import { Lightbulb, MessageSquareQuote, Star, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    DIMENSIONS,
    type FeedbackItem,
    type MyFeedback,
    type Rating,
    type ToRate,
} from './types';

/** One to five stars for one area, as a labelled group of buttons. */
function Stars({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: number;
    onChange: (n: number) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2">
            <p id={id} className="text-base font-medium">
                {label}
            </p>
            <ToggleGroup
                type="single"
                aria-labelledby={id}
                value={value > 0 ? String(value) : ''}
                onValueChange={(v) => onChange(Number(v) || 0)}
                className="flex gap-1"
            >
                {[1, 2, 3, 4, 5].map((n) => (
                    <ToggleGroupItem
                        key={n}
                        value={String(n)}
                        aria-label={t('feedback.stars', { count: n })}
                        className="size-12"
                    >
                        <Star
                            aria-hidden
                            className={
                                n <= value
                                    ? 'size-5 fill-current text-reos-tone-warning-fg'
                                    : 'size-5'
                            }
                        />
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
        </div>
    );
}

/** Rate the other side's agent after a closing. */
export function RateAgentForm({
    row,
    busy = false,
    onSend,
}: {
    row: ToRate;
    busy?: boolean;
    onSend: (row: ToRate, rating: Rating) => void;
}) {
    const { t, i18n } = useTranslation();
    const [r, setR] = useState<Rating>({
        communication: 0,
        responsiveness: 0,
        professionalism: 0,
        comment: '',
    });
    const ready = DIMENSIONS.every((d) => r[d] > 0);
    const key = `${row.deal}-${row.agent.id}`;

    return (
        <form
            aria-labelledby={`rate-${key}`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onSend(row, r);
            }}
        >
            <div className="space-y-1">
                <h3 id={`rate-${key}`} className="text-base font-semibold">
                    {t('feedback.rate', { name: row.agent.name })}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {row.property}
                    {row.closed_at
                        ? ` · ${t('feedback.closed', { date: new Intl.DateTimeFormat(i18n.language, { month: 'long', day: 'numeric' }).format(new Date(row.closed_at)) })}`
                        : ''}
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                {DIMENSIONS.map((d) => (
                    <Stars
                        key={d}
                        id={`${key}-${d}`}
                        label={t(`feedback.dimensions.${d}`)}
                        value={r[d]}
                        onChange={(n) => setR({ ...r, [d]: n })}
                    />
                ))}
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${key}-comment`}>
                    {t('feedback.comment')}
                </Label>
                <Textarea
                    id={`${key}-comment`}
                    value={r.comment}
                    maxLength={1000}
                    aria-describedby={`${key}-hint`}
                    onChange={(e) => setR({ ...r, comment: e.target.value })}
                />
                <p id={`${key}-hint`} className="text-sm text-muted-foreground">
                    {t('feedback.commentHint')}
                </p>
            </div>
            <Button type="submit" disabled={busy || !ready}>
                {t('feedback.send')}
            </Button>
        </form>
    );
}

/** Agents to rate (screen 125, the rating side). */
export function ToRateList({
    rows,
    busy = false,
    onSend,
}: {
    rows: ToRate[];
    busy?: boolean;
    onSend: (row: ToRate, rating: Rating) => void;
}) {
    const { t } = useTranslation();

    if (rows.length === 0) {
        return (
            <EmptyState
                icon={Users}
                title={t('feedback.noneToRate')}
                description={t('feedback.noneToRateHint')}
            />
        );
    }

    return (
        <div className="space-y-4">
            {rows.map((row) => (
                <RateAgentForm
                    key={`${row.deal}-${row.agent.id}`}
                    row={row}
                    busy={busy}
                    onSend={onSend}
                />
            ))}
        </div>
    );
}

function FeedbackRow({
    item,
    busy,
    onRespond,
    onReport,
}: {
    item: FeedbackItem;
    busy: boolean;
    onRespond: (item: FeedbackItem, text: string) => void;
    onReport: (item: FeedbackItem, reason: string) => void;
}) {
    const { t, i18n } = useTranslation();
    const [mode, setMode] = useState<'none' | 'respond' | 'report'>('none');
    const [text, setText] = useState(item.response ?? '');
    const [reason, setReason] = useState('');
    const month = new Intl.DateTimeFormat(i18n.language, {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${item.month}-15T12:00:00Z`));

    return (
        <li className="space-y-3 rounded-reos-md border border-border bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">{month}</p>
                {item.reported ? (
                    <Badge variant="outline">
                        {t('feedback.reportedBadge')}
                    </Badge>
                ) : null}
            </div>
            <p className="flex flex-wrap gap-x-4 text-base">
                {DIMENSIONS.map((d) => (
                    <span key={d}>
                        {t('feedback.scoreLine', {
                            label: t(`feedback.dimensions.${d}`),
                            score: item[d],
                        })}
                    </span>
                ))}
            </p>
            {item.comment ? (
                <blockquote className="border-l-4 border-border pl-3 text-base">
                    {item.comment}
                </blockquote>
            ) : null}
            {item.response && mode !== 'respond' ? (
                <p className="text-base">
                    <span className="font-semibold">
                        {t('feedback.yourResponse')}:{' '}
                    </span>
                    {item.response}
                </p>
            ) : null}
            {mode === 'respond' ? (
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onRespond(item, text.trim());
                        setMode('none');
                    }}
                >
                    <Label htmlFor={`respond-${item.id}`}>
                        {t('feedback.yourResponse')}
                    </Label>
                    <Textarea
                        id={`respond-${item.id}`}
                        value={text}
                        maxLength={500}
                        onChange={(e) => setText(e.target.value)}
                    />
                    <Button
                        type="submit"
                        disabled={busy || text.trim().length < 2}
                    >
                        {t('feedback.saveResponse')}
                    </Button>
                </form>
            ) : null}
            {mode === 'report' ? (
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onReport(item, reason.trim());
                        setMode('none');
                    }}
                >
                    <Label htmlFor={`report-${item.id}`}>
                        {t('feedback.reportReason')}
                    </Label>
                    <Textarea
                        id={`report-${item.id}`}
                        value={reason}
                        maxLength={300}
                        onChange={(e) => setReason(e.target.value)}
                    />
                    <Button
                        type="submit"
                        variant="destructive"
                        disabled={busy || reason.trim().length < 3}
                    >
                        {t('feedback.reportSend')}
                    </Button>
                </form>
            ) : null}
            {mode === 'none' ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        onClick={() => setMode('respond')}
                    >
                        {t('feedback.respond')}
                    </Button>
                    {!item.reported ? (
                        <Button
                            variant="ghost"
                            onClick={() => setMode('report')}
                        >
                            {t('feedback.report')}
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </li>
    );
}

/** My feedback: nothing until three ratings, then averages, comments without names and a tip. */
export function MyFeedbackPanel({
    mine,
    busy = false,
    onPublic,
    onRespond,
    onReport,
}: {
    mine: MyFeedback;
    busy?: boolean;
    onPublic: (value: boolean) => void;
    onRespond: (item: FeedbackItem, text: string) => void;
    onReport: (item: FeedbackItem, reason: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="space-y-4">
            {!mine.shown ? (
                <EmptyState
                    icon={MessageSquareQuote}
                    title={
                        mine.count === 0
                            ? t('feedback.none')
                            : t('feedback.waiting', {
                                  count: mine.count,
                                  needed: mine.needed,
                              })
                    }
                    description={t('feedback.toRateHint')}
                />
            ) : (
                <>
                    {mine.average ? (
                        <section
                            aria-labelledby="average-title"
                            className="space-y-2 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <h3
                                id="average-title"
                                className="text-2xl font-semibold"
                            >
                                {t('feedback.average', {
                                    average: mine.average.overall.toFixed(1),
                                    count: mine.count,
                                })}
                            </h3>
                            <p className="flex flex-wrap gap-x-4 text-base">
                                {DIMENSIONS.map((d) => (
                                    <span key={d}>
                                        {t('feedback.scoreLine', {
                                            label: t(
                                                `feedback.dimensions.${d}`,
                                            ),
                                            score: mine.average?.[d].toFixed(1),
                                        })}
                                    </span>
                                ))}
                            </p>
                        </section>
                    ) : null}
                    {mine.tip ? (
                        <section
                            aria-labelledby="tip-title"
                            className="flex gap-3 rounded-reos-lg bg-reos-tone-info-bg p-5 text-reos-tone-info-fg"
                        >
                            <Lightbulb
                                aria-hidden
                                className="mt-1 size-5 shrink-0"
                            />
                            <div className="space-y-1">
                                <h3
                                    id="tip-title"
                                    className="text-base font-semibold"
                                >
                                    {t('feedback.tip')}:{' '}
                                    {t(
                                        `feedback.dimensions.${mine.tip.dimension}`,
                                    )}
                                </h3>
                                <p className="text-base">{mine.tip.text}</p>
                            </div>
                        </section>
                    ) : null}
                    <ul className="space-y-3">
                        {mine.items.map((item) => (
                            <FeedbackRow
                                key={item.id}
                                item={item}
                                busy={busy}
                                onRespond={onRespond}
                                onReport={onReport}
                            />
                        ))}
                    </ul>
                </>
            )}
            <div className="flex items-start justify-between gap-4 rounded-reos-md border border-border bg-card p-4">
                <div className="space-y-1">
                    <Label htmlFor="feedback-public" className="text-base">
                        {t('feedback.public')}
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        {t('feedback.publicHint')}
                    </p>
                </div>
                <Switch
                    id="feedback-public"
                    checked={mine.public}
                    disabled={busy}
                    onCheckedChange={onPublic}
                />
            </div>
        </div>
    );
}
