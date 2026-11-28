import { CircleCheck, ShieldCheck, TriangleAlert } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { putJson } from '@/lib/content';
import type {
    OversightAgent,
    OversightBoardData,
    OversightProfile,
    OversightSignal,
    RiskDeal,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const KINDS = [
    'option_period',
    'financing',
    'appraisal',
    'inspection',
    'survey',
    'title_commitment',
    'closing',
    'other',
] as const;
const STAGES = ['active', 'under_contract', 'closed', 'cancelled'] as const;
const SIDES = ['buy', 'sell', 'lease', 'both'] as const;

function pick<T extends string>(
    list: readonly T[],
    value: string | null | undefined,
): T | null {
    return list.find((v) => v === value) ?? null;
}

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(
            iso.length === 10 ? `${iso}T12:00:00` : iso,
        ).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
}

/** The reasons an agent may need help, each in plain words. */
export function SignalList({
    signals,
    settings,
}: {
    signals: OversightSignal[];
    settings: OversightBoardData['settings'];
}) {
    const { t } = useTranslation();
    const label = (s: OversightSignal): string => {
        switch (s) {
            case 'quiet':
                return t('brokerage.oversight.signals.quiet', {
                    days: settings.quiet_days,
                });
            case 'no_deal':
                return t('brokerage.oversight.signals.no_deal', {
                    days: settings.no_deal_days,
                });
            case 'ce_behind':
                return t('brokerage.oversight.signals.ce_behind');
            case 'plan_behind':
                return t('brokerage.oversight.signals.plan_behind');
            case 'deadline_missed':
                return t('brokerage.oversight.signals.deadline_missed');
            case 'new':
                return t('brokerage.oversight.signals.new');
        }
    };

    return (
        <ul className="flex flex-wrap gap-2">
            {signals.map((s) => (
                <li
                    key={s}
                    className={
                        s === 'new'
                            ? 'rounded-full bg-muted px-3 py-1 text-sm'
                            : 'rounded-full bg-reos-tone-warning-bg px-3 py-1 text-sm text-reos-tone-warning-fg'
                    }
                >
                    {label(s)}
                </li>
            ))}
        </ul>
    );
}

function AgentRow({
    agent,
    settings,
    href,
}: {
    agent: OversightAgent;
    settings: OversightBoardData['settings'];
    href: (id: number) => string;
}) {
    const { t } = useTranslation();
    const date = useDate();

    return (
        <li className="space-y-2 py-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <p className="font-medium">
                        {agent.name}
                        {agent.team ? (
                            <span className="text-muted-foreground">
                                {' '}
                                · {agent.team}
                            </span>
                        ) : null}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {agent.last_active
                            ? t('brokerage.oversight.lastActive', {
                                  date: date(agent.last_active),
                              })
                            : t('brokerage.oversight.neverActive')}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.oversight.deals', {
                            active: agent.deals.active,
                            underContract: agent.deals.under_contract,
                            closed: agent.deals.closed_90,
                        })}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <a href={href(agent.id)}>
                        {t('brokerage.oversight.open', { name: agent.name })}
                    </a>
                </Button>
            </div>
            {agent.signals.length > 0 ? (
                <SignalList signals={agent.signals} settings={settings} />
            ) : null}
        </li>
    );
}

function RiskRow({
    deal,
    href,
}: {
    deal: RiskDeal;
    href: (id: number) => string;
}) {
    const { t } = useTranslation();
    const date = useDate();
    const stage = pick(STAGES, deal.stage) ?? 'active';
    const kind = pick(KINDS, deal.next_date?.kind) ?? 'other';
    const reason = (r: RiskDeal['reasons'][number]): string =>
        r === 'date_soon'
            ? t('brokerage.oversight.reasons.date_soon')
            : r === 'date_missed'
              ? t('brokerage.oversight.reasons.date_missed')
              : t('brokerage.oversight.reasons.stuck');

    return (
        <li className="space-y-1 py-4">
            <p className="font-medium">
                <a href={href(deal.agent_id)} className="underline">
                    {deal.agent}
                </a>{' '}
                · {t(`brokerage.oversight.stages.${stage}`)}
                {deal.address
                    ? ` · ${deal.address}`
                    : deal.city
                      ? ` · ${deal.city}`
                      : ''}
            </p>
            <p className="flex flex-wrap items-center gap-2 text-base text-reos-tone-warning-fg">
                <TriangleAlert aria-hidden className="size-4" />
                {deal.reasons.map(reason).join(' · ')}
            </p>
            {deal.next_date ? (
                <p className="text-base text-muted-foreground">
                    {t('brokerage.oversight.nextDate', {
                        kind: t(`deals.kinds.${kind}`),
                        date: date(deal.next_date.due_on),
                    })}
                </p>
            ) : null}
        </li>
    );
}

/**
 * Agents and deals (A16.1, broker web screen "agents needing help"): who
 * may need help and why, and deals at risk. Counts and stages only; the
 * agent's clients, contacts and notes stay with the agent.
 */
export function OversightBoard({
    board: initial,
    routes,
}: {
    board: OversightBoardData;
    routes: { agent: (id: number) => string; settings: string };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [board, setBoard] = useState(initial);
    const [settings, setSettings] = useState({
        quiet: String(initial.settings.quiet_days),
        noDeal: String(initial.settings.no_deal_days),
        new: String(initial.settings.new_days),
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const help = board.agents.filter((a) => a.signals.some((s) => s !== 'new'));
    const rest = board.agents.filter(
        (a) => !a.signals.some((s) => s !== 'new'),
    );

    return (
        <div className="space-y-6">
            <p className="text-base text-muted-foreground">
                {t('brokerage.oversight.counts', {
                    needHelp: board.counts.need_help,
                    agents: board.counts.agents,
                    deals: board.counts.deals_at_risk,
                })}
            </p>
            <p className="flex gap-2 rounded-reos-md bg-muted p-3 text-base">
                <ShieldCheck aria-hidden className="mt-0.5 size-5 shrink-0" />
                {t('brokerage.oversight.privacy')}
            </p>
            <div className="grid gap-4 lg:grid-cols-2">
                <section aria-labelledby={`${ids}-help`} className={card}>
                    <h2 id={`${ids}-help`} className="font-semibold">
                        {t('brokerage.oversight.needHelp')}
                    </h2>
                    {help.length === 0 ? (
                        <EmptyState
                            icon={CircleCheck}
                            title={t('brokerage.oversight.allGood')}
                            description={t('brokerage.oversight.allGoodHint')}
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {help.map((a) => (
                                <AgentRow
                                    key={a.id}
                                    agent={a}
                                    settings={board.settings}
                                    href={routes.agent}
                                />
                            ))}
                        </ul>
                    )}
                </section>
                <section aria-labelledby={`${ids}-risk`} className={card}>
                    <h2 id={`${ids}-risk`} className="font-semibold">
                        {t('brokerage.oversight.risk')}
                    </h2>
                    {board.at_risk.length === 0 ? (
                        <EmptyState
                            icon={CircleCheck}
                            title={t('brokerage.oversight.noRisk')}
                            description={t('brokerage.oversight.noRiskHint')}
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {board.at_risk.map((d) => (
                                <RiskRow
                                    key={d.id}
                                    deal={d}
                                    href={routes.agent}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
            {rest.length > 0 ? (
                <section aria-labelledby={`${ids}-rest`} className={card}>
                    <h2 id={`${ids}-rest`} className="font-semibold">
                        {t('brokerage.oversight.onTrack')}
                    </h2>
                    <ul className="divide-y divide-border">
                        {rest.map((a) => (
                            <AgentRow
                                key={a.id}
                                agent={a}
                                settings={board.settings}
                                href={routes.agent}
                            />
                        ))}
                    </ul>
                </section>
            ) : null}
            {board.can_change ? (
                <section aria-labelledby={`${ids}-settings`} className={card}>
                    <h2 id={`${ids}-settings`} className="font-semibold">
                        {t('brokerage.oversight.settings.title')}
                    </h2>
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setBusy(true);
                            setErrors({});
                            putJson<{ data: OversightBoardData }>(
                                routes.settings,
                                {
                                    quiet_days: Number(settings.quiet),
                                    no_deal_days: Number(settings.noDeal),
                                    new_days: Number(settings.new),
                                },
                            )
                                .then((res) => {
                                    setBoard(res.data);
                                    toast.success(
                                        t('brokerage.oversight.settings.saved'),
                                    );
                                })
                                .catch((err: unknown) =>
                                    setErrors(
                                        typeof err === 'object' &&
                                            err !== null &&
                                            'errors' in err
                                            ? Object.fromEntries(
                                                  Object.entries(
                                                      (
                                                          err as {
                                                              errors: Record<
                                                                  string,
                                                                  string[]
                                                              >;
                                                          }
                                                      ).errors,
                                                  ).map(([k, v]) => [
                                                      k,
                                                      v[0] ?? '',
                                                  ]),
                                              )
                                            : { form: String(err) },
                                    ),
                                )
                                .finally(() => setBusy(false));
                        }}
                    >
                        {(
                            [
                                [
                                    'quiet',
                                    t('brokerage.oversight.settings.quiet'),
                                    3,
                                    60,
                                ],
                                [
                                    'noDeal',
                                    t('brokerage.oversight.settings.noDeal'),
                                    30,
                                    365,
                                ],
                                [
                                    'new',
                                    t('brokerage.oversight.settings.new'),
                                    30,
                                    365,
                                ],
                            ] as const
                        ).map(([key, label, min, max]) => (
                            <div key={key} className="grid gap-2">
                                <Label htmlFor={`${ids}-${key}`}>{label}</Label>
                                <Input
                                    id={`${ids}-${key}`}
                                    type="number"
                                    min={min}
                                    max={max}
                                    className="w-32"
                                    value={settings[key]}
                                    onChange={(e) =>
                                        setSettings({
                                            ...settings,
                                            [key]: e.target.value,
                                        })
                                    }
                                />
                            </div>
                        ))}
                        <Button type="submit" variant="outline" disabled={busy}>
                            {t('brokerage.oversight.settings.save')}
                        </Button>
                        <InputError
                            className="w-full"
                            message={
                                errors.quiet_days ??
                                errors.no_deal_days ??
                                errors.new_days ??
                                errors.form
                            }
                        />
                    </form>
                </section>
            ) : null}
        </div>
    );
}

/** An agent as their broker sees them (A16.1), and the agent's own "What my broker sees". */
export function OversightProfileView({
    profile,
    settings,
}: {
    profile: OversightProfile;
    settings: OversightBoardData['settings'];
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const a = profile.agent;

    return (
        <div className="space-y-6">
            {a.signals.length > 0 ? (
                <SignalList signals={a.signals} settings={settings} />
            ) : null}
            <div className="grid gap-4 md:grid-cols-2">
                <section aria-labelledby={`${ids}-month`} className={card}>
                    <h2 id={`${ids}-month`} className="font-semibold">
                        {t('brokerage.oversight.profile.month')}
                    </h2>
                    <dl className="grid grid-cols-2 gap-3">
                        {(
                            [
                                [
                                    t(
                                        'brokerage.oversight.profile.conversations',
                                    ),
                                    profile.activity.conversations,
                                ],
                                [
                                    t(
                                        'brokerage.oversight.profile.appointments',
                                    ),
                                    profile.activity.appointments,
                                ],
                                [
                                    t('brokerage.oversight.profile.posts'),
                                    profile.activity.posts,
                                ],
                                [
                                    t('brokerage.oversight.profile.ceHours'),
                                    profile.activity.ce_hours,
                                ],
                            ] as const
                        ).map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-base text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-title font-semibold">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {profile.plan ? (
                        <p className="text-base">
                            {t('brokerage.oversight.profile.plan')}:{' '}
                            {t('brokerage.oversight.profile.planWeek', {
                                week: profile.plan.week,
                                weeks: profile.plan.weeks,
                                done: profile.plan.done,
                                total: profile.plan.total,
                            })}
                        </p>
                    ) : null}
                </section>
                <section aria-labelledby={`${ids}-licences`} className={card}>
                    <h2 id={`${ids}-licences`} className="font-semibold">
                        {t('brokerage.oversight.profile.licences')}
                    </h2>
                    <ul className="space-y-1 text-base">
                        {profile.licences.map((l) => (
                            <li key={`${l.state}${l.number}`}>
                                {l.state} {l.number} · {l.status}
                                {l.expires_on ? ` · ${date(l.expires_on)}` : ''}
                            </li>
                        ))}
                    </ul>
                    {a.ce ? (
                        <p className="text-base">
                            {a.ce.first_renewal
                                ? `${t('brokerage.oversight.sae')} · `
                                : ''}
                            {a.ce.complete
                                ? t('brokerage.oversight.ceDone')
                                : t('brokerage.oversight.ceDue', {
                                      hours: a.ce.hours_left,
                                      date: a.ce.due_on
                                          ? date(a.ce.due_on)
                                          : '—',
                                  })}
                        </p>
                    ) : null}
                </section>
            </div>
            <section aria-labelledby={`${ids}-deals`} className={card}>
                <h2 id={`${ids}-deals`} className="font-semibold">
                    {t('brokerage.oversight.profile.deals')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('brokerage.oversight.privacy')}
                </p>
                {profile.deals.length === 0 ? (
                    <p className="text-base">
                        {t('brokerage.oversight.profile.noDeals')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {profile.deals.map((d) => (
                            <li key={d.id} className="py-3 text-base">
                                {t('brokerage.oversight.profile.dealRow', {
                                    stage: t(
                                        `brokerage.oversight.stages.${pick(STAGES, d.stage) ?? 'active'}`,
                                    ),
                                    side: t(
                                        `brokerage.oversight.sides.${pick(SIDES, d.side) ?? 'buy'}`,
                                    ),
                                    place: d.address ?? d.city ?? '—',
                                })}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}
