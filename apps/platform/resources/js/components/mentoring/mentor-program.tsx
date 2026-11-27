import { GraduationCap, Star, UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { nativeSelect } from '@/lib/tenants';
import type {
    AgentRow,
    MatchRow,
    MentorProgramData,
    MentorRow,
    MentorSettings,
    Topic,
    Who,
} from './types';

const usd = (cents: number, lang: string) =>
    new Intl.NumberFormat(lang, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
    }).format(cents / 100);

/** One mentor's settings (broker screen 22): topics, prices, points, max mentees, who can book. */
export function MentorSettingsForm({
    mentor,
    topics,
    busy = false,
    onSave,
}: {
    mentor: MentorRow;
    topics: Topic[];
    busy?: boolean;
    onSave: (values: MentorSettings) => void;
}) {
    const { t } = useTranslation();
    const [values, setValues] = useState<MentorSettings>({
        topics: mentor.topics,
        price_30_cents: mentor.price_30,
        price_60_cents: mentor.price_60,
        points: mentor.points,
        max_mentees: mentor.max_mentees,
        who: mentor.who,
    });
    const id = (key: string) => `mentor-${mentor.id}-${key}`;

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                onSave(values);
            }}
        >
            <fieldset className="space-y-2">
                <legend className="text-base font-medium">
                    {t('mentoring.program.focus')}
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {topics.map((topic) => (
                        <div
                            key={topic}
                            className="flex min-h-12 items-center gap-3"
                        >
                            <Checkbox
                                id={id(topic)}
                                checked={values.topics.includes(topic)}
                                onCheckedChange={(on) =>
                                    setValues((v) => ({
                                        ...v,
                                        topics:
                                            on === true
                                                ? [...v.topics, topic]
                                                : v.topics.filter(
                                                      (x) => x !== topic,
                                                  ),
                                    }))
                                }
                            />
                            <Label htmlFor={id(topic)}>
                                {t(`mentoring.topics.${topic}`)}
                            </Label>
                        </div>
                    ))}
                </div>
            </fieldset>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor={id('p30')}>
                        {t('mentoring.program.price30')}
                    </Label>
                    <Input
                        id={id('p30')}
                        type="number"
                        min={0}
                        step={1}
                        value={values.price_30_cents / 100}
                        onChange={(e) =>
                            setValues((v) => ({
                                ...v,
                                price_30_cents: Math.round(
                                    Number(e.target.value) * 100,
                                ),
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor={id('p60')}>
                        {t('mentoring.program.price60')}
                    </Label>
                    <Input
                        id={id('p60')}
                        type="number"
                        min={0}
                        step={1}
                        value={values.price_60_cents / 100}
                        onChange={(e) =>
                            setValues((v) => ({
                                ...v,
                                price_60_cents: Math.round(
                                    Number(e.target.value) * 100,
                                ),
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor={id('max')}>
                        {t('mentoring.program.maxMentees')}
                    </Label>
                    <Input
                        id={id('max')}
                        type="number"
                        min={1}
                        max={30}
                        value={values.max_mentees}
                        onChange={(e) =>
                            setValues((v) => ({
                                ...v,
                                max_mentees: Number(e.target.value),
                            }))
                        }
                    />
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="flex min-h-12 items-center gap-3">
                    <Switch
                        id={id('points')}
                        checked={values.points}
                        onCheckedChange={(points) =>
                            setValues((v) => ({ ...v, points }))
                        }
                    />
                    <Label htmlFor={id('points')}>
                        {t('mentoring.program.points')}
                    </Label>
                </div>
                <div className="space-y-2">
                    <Label htmlFor={id('who')}>
                        {t('mentoring.program.who')}
                    </Label>
                    <select
                        id={id('who')}
                        className={nativeSelect}
                        value={values.who}
                        onChange={(e) =>
                            setValues((v) => ({
                                ...v,
                                who: e.target.value as Who,
                            }))
                        }
                    >
                        <option value="everyone">
                            {t('mentoring.program.whoEveryone')}
                        </option>
                        <option value="assigned">
                            {t('mentoring.program.whoAssigned')}
                        </option>
                    </select>
                </div>
            </div>
            <p className="text-sm text-muted-foreground">
                {t('mentoring.program.priceNote')}
            </p>
            <Button type="submit" disabled={busy}>
                {t('mentoring.program.save')}
            </Button>
        </form>
    );
}

/** Mentors (screen 21): focus, load, price, points and rating, with Settings. */
export function MentorList({
    data,
    busy = false,
    onSave,
}: {
    data: MentorProgramData;
    busy?: boolean;
    onSave: (mentor: MentorRow, values: MentorSettings) => void;
}) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState<number | null>(null);

    if (data.mentors.length === 0) {
        return (
            <EmptyState
                icon={GraduationCap}
                title={t('mentoring.program.noMentors')}
                description={t('mentoring.program.noMentorsHint')}
            />
        );
    }

    return (
        <ul className="space-y-3">
            {data.mentors.map((m) => (
                <li
                    key={m.id}
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <p className="text-lg font-semibold">{m.name}</p>
                            <p className="text-base text-muted-foreground">
                                {m.topics.length > 0
                                    ? m.topics
                                          .map((x) =>
                                              t(`mentoring.topics.${x}`),
                                          )
                                          .join(' · ')
                                    : t('mentoring.program.focus')}
                            </p>
                            <div className="flex flex-wrap gap-2 text-sm">
                                <Badge variant="outline">
                                    {t('mentoring.program.load')}:{' '}
                                    {t('mentoring.program.loadValue', {
                                        count: m.mentees,
                                        max: m.max_mentees,
                                    })}
                                </Badge>
                                <Badge variant="outline">
                                    {t('mentoring.program.price')}:{' '}
                                    {m.price_60 === 0
                                        ? t('mentoring.program.free')
                                        : `${usd(m.price_30, i18n.language)} / ${usd(m.price_60, i18n.language)}`}
                                </Badge>
                                {m.points ? (
                                    <Badge variant="outline">
                                        {t('mentoring.agent.points')}
                                    </Badge>
                                ) : null}
                                <Badge variant="outline">
                                    <Star aria-hidden className="size-4" />
                                    {m.rating === null
                                        ? t('mentoring.program.noRating')
                                        : `${m.rating} (${m.ratings})`}
                                </Badge>
                                {m.hours_set ? null : (
                                    <Badge variant="outline">
                                        {t('mentoring.program.hoursMissing')}
                                    </Badge>
                                )}
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            aria-expanded={open === m.id}
                            onClick={() => setOpen(open === m.id ? null : m.id)}
                        >
                            {t('mentoring.program.settings')}
                        </Button>
                    </div>
                    {open === m.id ? (
                        <MentorSettingsForm
                            mentor={m}
                            topics={data.topics}
                            busy={busy}
                            onSave={(values) => onSave(m, values)}
                        />
                    ) : null}
                </li>
            ))}
        </ul>
    );
}

/** Agents and their mentors, with Assign and the best matches (screen 23). */
export function AgentAssignments({
    agents,
    busy = false,
    onMatches,
    onAssign,
}: {
    agents: AgentRow[];
    busy?: boolean;
    onMatches: (agent: AgentRow) => Promise<MatchRow[]>;
    onAssign: (agent: AgentRow, mentor: MatchRow) => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState<{
        agent: number;
        matches: MatchRow[];
    } | null>(null);

    if (agents.length === 0) {
        return (
            <EmptyState
                icon={Users}
                title={t('mentoring.program.noAgents')}
                description={t('mentoring.program.addHint')}
            />
        );
    }

    return (
        <ul className="space-y-3">
            {agents.map((a) => (
                <li
                    key={a.id}
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-1">
                            <p className="text-base font-semibold">{a.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {t(`mentoring.goals.${a.goal}`)} ·{' '}
                                {a.mentor
                                    ? a.mentor.name
                                    : t('mentoring.program.noMentor')}
                                {a.change_requested
                                    ? ` · ${t('mentoring.program.changeAsked')}`
                                    : ''}
                            </p>
                        </div>
                        <Button
                            variant={a.mentor ? 'outline' : 'default'}
                            disabled={busy}
                            onClick={() =>
                                void onMatches(a).then((matches) =>
                                    setOpen({ agent: a.id, matches }),
                                )
                            }
                        >
                            <UserCheck aria-hidden />
                            {a.mentor
                                ? t('mentoring.program.reassign')
                                : t('mentoring.program.assign')}
                        </Button>
                    </div>
                    {open?.agent === a.id ? (
                        <div className="space-y-2" aria-live="polite">
                            <p className="text-base font-medium">
                                {t('mentoring.program.matches', {
                                    name: a.name,
                                })}
                            </p>
                            {open.matches.length === 0 ? (
                                <p className="text-base text-muted-foreground">
                                    {t('mentoring.program.noMatches')}
                                </p>
                            ) : null}
                            <ul className="space-y-2">
                                {open.matches.map((m) => (
                                    <li
                                        key={m.id}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-reos-md border border-border p-3"
                                    >
                                        <div>
                                            <p className="text-base">
                                                {m.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {m.reasons
                                                    .map((r) =>
                                                        t(
                                                            `mentoring.reasons.${r}`,
                                                        ),
                                                    )
                                                    .join(' · ')}{' '}
                                                ·{' '}
                                                {t('mentoring.program.room', {
                                                    count: m.room,
                                                })}
                                            </p>
                                        </div>
                                        <Button
                                            disabled={
                                                busy || a.mentor?.id === m.id
                                            }
                                            onClick={() => {
                                                onAssign(a, m);
                                                setOpen(null);
                                            }}
                                        >
                                            {t('mentoring.program.assign')}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
