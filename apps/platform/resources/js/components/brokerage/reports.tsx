import {
    ChartColumn,
    CircleCheck,
    GraduationCap,
    RefreshCw,
    Sun,
    TriangleAlert,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { SignalList } from './oversight-board';
import type {
    BrokerBrief,
    ClosingsReport as ClosingsData,
    ReportPeriod,
    TrainingReport as TrainingData,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const PERIODS: readonly ReportPeriod[] = [
    'this_month',
    'last_month',
    'this_quarter',
    'this_year',
    'last_year',
];
const REASONS = ['date_soon', 'date_missed', 'stuck'] as const;
const STAGES = ['active', 'under_contract', 'closed', 'cancelled'] as const;

function pick<T extends string>(list: readonly T[], value: string): T | null {
    return list.find((v) => v === value) ?? null;
}

function useFormat() {
    const { i18n } = useTranslation();
    const money = new Intl.NumberFormat(i18n.language, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });

    return {
        money: (n: number) => money.format(n),
        date: (iso: string) =>
            new Date(
                iso.length === 10 ? `${iso}T12:00:00` : iso,
            ).toLocaleDateString(i18n.language, { dateStyle: 'medium' }),
        month: (ym: string) =>
            new Date(`${ym}-15T12:00:00`).toLocaleDateString(i18n.language, {
                month: 'long',
                year: 'numeric',
            }),
    };
}

function PeriodPicker({
    period,
    href,
}: {
    period: ReportPeriod;
    href: (period: ReportPeriod) => string;
}) {
    const { t } = useTranslation();
    const label: Record<ReportPeriod, string> = {
        this_month: t('brokerage.reports.periods.this_month'),
        last_month: t('brokerage.reports.periods.last_month'),
        this_quarter: t('brokerage.reports.periods.this_quarter'),
        this_year: t('brokerage.reports.periods.this_year'),
        last_year: t('brokerage.reports.periods.last_year'),
    };

    return (
        <nav aria-label={t('brokerage.reports.period')}>
            <ul className="flex flex-wrap gap-2">
                {PERIODS.map((p) => (
                    <li key={p}>
                        <Button
                            asChild
                            variant={p === period ? 'default' : 'outline'}
                        >
                            <a
                                href={href(p)}
                                aria-current={p === period ? 'page' : undefined}
                            >
                                {label[p]}
                            </a>
                        </Button>
                    </li>
                ))}
            </ul>
        </nav>
    );
}

function LineTable({
    caption,
    head,
    rows,
}: {
    caption: string;
    head: string;
    rows: { key: string; name: string; count: number; volume: number }[];
}) {
    const { t } = useTranslation();
    const f = useFormat();

    return (
        <section className={card} aria-label={caption}>
            <h2 className="text-title font-semibold">{caption}</h2>
            <table className="w-full text-left text-base">
                <thead className="text-muted-foreground">
                    <tr>
                        <th scope="col" className="py-2 font-medium">
                            {head}
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            {t('brokerage.reports.closings')}
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            {t('brokerage.reports.volume')}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((r) => (
                        <tr key={r.key}>
                            <td className="py-3">{r.name}</td>
                            <td className="py-3 text-right tabular-nums">
                                {r.count}
                            </td>
                            <td className="py-3 text-right tabular-nums">
                                {f.money(r.volume)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}

/** How the closings line up with the transaction tool, and what's only in REOS. */
function ToolMatch({ report }: { report: ClosingsData }) {
    const { t } = useTranslation();
    const f = useFormat();

    return (
        <section className={card} aria-labelledby="reports-match">
            <h2 id="reports-match" className="text-title font-semibold">
                {t('brokerage.reports.match.title')}
            </h2>
            {report.tools.length === 0 ? (
                <div className="space-y-1">
                    <p className="font-medium">
                        {t('brokerage.reports.match.noTool')}
                    </p>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.reports.match.noToolHint')}
                    </p>
                </div>
            ) : (
                <ul className="divide-y divide-border">
                    {report.tools.map((tool) => (
                        <li key={tool.connection} className="space-y-1 py-3">
                            <p className="font-medium">
                                {t('brokerage.reports.match.tool', {
                                    tool: tool.name,
                                    count: tool.count,
                                    volume: f.money(tool.volume),
                                })}
                            </p>
                            <p className="flex items-center gap-2 text-base text-muted-foreground">
                                <RefreshCw aria-hidden className="size-4" />
                                {tool.last_synced_at
                                    ? t('brokerage.reports.match.synced', {
                                          date: f.date(tool.last_synced_at),
                                      })
                                    : t('brokerage.reports.match.neverSynced')}
                            </p>
                            {tool.stale ? (
                                <p className="text-base text-reos-tone-warning-fg">
                                    {t('brokerage.reports.match.stale')}
                                </p>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            {report.reos_only.count === 0 ? (
                report.tools.length > 0 ? (
                    <p className="flex items-center gap-2 text-base">
                        <CircleCheck
                            aria-hidden
                            className="size-5 text-reos-tone-success-fg"
                        />
                        {t('brokerage.reports.match.matched')}
                    </p>
                ) : null
            ) : (
                <div className="space-y-2">
                    <h3 className="font-semibold">
                        {t('brokerage.reports.match.differences', {
                            count: report.reos_only.count,
                        })}
                    </h3>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.reports.match.differencesHint')}
                    </p>
                    <ul className="space-y-2">
                        {report.reos_only.deals.map((d) => (
                            <li
                                key={d.id}
                                className="rounded-reos-md bg-reos-tone-warning-bg px-3 py-2 text-base text-reos-tone-warning-fg"
                            >
                                {t('brokerage.reports.match.difference', {
                                    property: d.property,
                                    agent: d.agent,
                                    price: f.money(d.price ?? 0),
                                    date: f.date(d.closed_at),
                                })}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}

/**
 * Closings report (A16.3): closings and volume by agent, team and month,
 * matched to the transaction tool.
 */
export function ClosingsReport({
    report,
    periodHref,
}: {
    report: ClosingsData;
    periodHref: (period: ReportPeriod) => string;
}) {
    const { t } = useTranslation();
    const f = useFormat();

    return (
        <div className="space-y-6">
            <PeriodPicker period={report.period} href={periodHref} />
            <p className="text-title font-semibold">
                {t('brokerage.reports.totals', {
                    count: report.totals.count,
                    volume: f.money(report.totals.volume),
                })}
            </p>
            <ToolMatch report={report} />
            {report.totals.count === 0 ? (
                <EmptyState
                    icon={ChartColumn}
                    title={t('brokerage.reports.empty')}
                    description={t('brokerage.reports.emptyHint')}
                />
            ) : (
                <div className="grid gap-6 lg:grid-cols-2">
                    <LineTable
                        caption={t('brokerage.reports.byAgent')}
                        head={t('brokerage.reports.agent')}
                        rows={report.by_agent.map((a) => ({
                            key: String(a.id),
                            name: a.name,
                            count: a.count,
                            volume: a.volume,
                        }))}
                    />
                    {report.scope === 'brokerage' ? (
                        <LineTable
                            caption={t('brokerage.reports.byTeam')}
                            head={t('brokerage.reports.team')}
                            rows={report.by_team.map((r) => ({
                                key: r.name ?? '',
                                name: r.name ?? t('brokerage.reports.noTeam'),
                                count: r.count,
                                volume: r.volume,
                            }))}
                        />
                    ) : null}
                    <LineTable
                        caption={t('brokerage.reports.byMonth')}
                        head={t('brokerage.reports.month')}
                        rows={report.by_month.map((m) => ({
                            key: m.month,
                            name: f.month(m.month),
                            count: m.count,
                            volume: m.volume,
                        }))}
                    />
                </div>
            )}
        </div>
    );
}

/** Training (A16.3): each assigned course, how many done and overdue. */
export function TrainingReport({
    report,
    ceHref,
}: {
    report: TrainingData;
    ceHref: string;
}) {
    const { t } = useTranslation();

    if (report.courses.length === 0) {
        return (
            <EmptyState
                icon={GraduationCap}
                title={t('brokerage.reports.training.empty')}
                description={t('brokerage.reports.training.emptyHint')}
                action={
                    <Button asChild>
                        <a href={ceHref}>{t('brokerage.brief.openCe')}</a>
                    </Button>
                }
            />
        );
    }

    return (
        <section className={card} aria-labelledby="reports-training">
            <h2 id="reports-training" className="text-title font-semibold">
                {t('brokerage.reports.training.title')}
            </h2>
            <ul className="divide-y divide-border">
                {report.courses.map((c) => (
                    <li
                        key={c.id}
                        className="flex flex-wrap items-center justify-between gap-2 py-3"
                    >
                        <div>
                            <p className="font-medium">{c.title}</p>
                            {c.ce_hours ? (
                                <p className="text-base text-muted-foreground">
                                    {t('brokerage.reports.training.ceHours', {
                                        hours: Number(c.ce_hours),
                                    })}
                                </p>
                            ) : null}
                        </div>
                        <p
                            className={cn(
                                'text-base',
                                c.overdue > 0
                                    ? 'text-reos-tone-danger-fg'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {t('brokerage.reports.training.progress', {
                                done: c.done,
                                assigned: c.assigned,
                                overdue: c.overdue,
                            })}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * The broker's morning brief (A16.3): who needs help, deals at risk,
 * approvals, CE due and closings, each linking to its screen.
 */
export function BrokerBriefCard({
    brief,
    links,
}: {
    brief: BrokerBrief;
    links: {
        oversight: string;
        approvals: string;
        ce: string;
        reports: string;
    };
}) {
    const { t } = useTranslation();
    const f = useFormat();
    const reason = (r: string) => {
        switch (pick(REASONS, r)) {
            case 'date_soon':
                return t('brokerage.oversight.reasons.date_soon');
            case 'date_missed':
                return t('brokerage.oversight.reasons.date_missed');
            case 'stuck':
                return t('brokerage.oversight.reasons.stuck');
            default:
                return r;
        }
    };
    const stage = (s: string) => {
        switch (pick(STAGES, s)) {
            case 'active':
                return t('brokerage.oversight.stages.active');
            case 'under_contract':
                return t('brokerage.oversight.stages.under_contract');
            case 'closed':
                return t('brokerage.oversight.stages.closed');
            case 'cancelled':
                return t('brokerage.oversight.stages.cancelled');
            default:
                return s;
        }
    };
    const clear =
        brief.need_help.count +
            brief.at_risk.count +
            brief.approvals.waiting +
            (brief.ce_due ?? 0) ===
        0;

    return (
        <section className={card} aria-labelledby="broker-brief">
            <div className="flex items-center gap-3">
                <Sun aria-hidden className="size-6 text-primary" />
                <div>
                    <h2 id="broker-brief" className="text-title font-semibold">
                        {t('brokerage.brief.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.brief.purpose', {
                            brokerage: brief.team ?? brief.brokerage,
                        })}
                    </p>
                </div>
            </div>
            {clear ? (
                <p className="flex items-center gap-2 text-base">
                    <CircleCheck
                        aria-hidden
                        className="size-5 text-reos-tone-success-fg"
                    />
                    {t('brokerage.brief.allClear')}
                </p>
            ) : null}
            <ul className="space-y-4">
                {brief.need_help.count > 0 ? (
                    <li className="space-y-2">
                        <p className="font-medium">
                            {t('brokerage.brief.needHelp', {
                                count: brief.need_help.count,
                            })}
                        </p>
                        <ul className="space-y-2">
                            {brief.need_help.agents.map((a) => (
                                <li key={a.id} className="space-y-1">
                                    <p>{a.name}</p>
                                    <SignalList
                                        signals={a.signals}
                                        settings={brief.settings}
                                    />
                                </li>
                            ))}
                        </ul>
                    </li>
                ) : null}
                {brief.at_risk.count > 0 ? (
                    <li className="space-y-2">
                        <p className="flex items-center gap-2 font-medium">
                            <TriangleAlert
                                aria-hidden
                                className="size-5 text-reos-tone-warning-fg"
                            />
                            {t('brokerage.brief.atRisk', {
                                count: brief.at_risk.count,
                            })}
                        </p>
                        <ul className="space-y-1 text-base text-muted-foreground">
                            {brief.at_risk.deals.map((d) => (
                                <li key={d.id}>
                                    {[
                                        d.agent,
                                        stage(d.stage),
                                        d.city,
                                        d.reasons.map(reason).join(', '),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </li>
                            ))}
                        </ul>
                    </li>
                ) : null}
                {brief.approvals.waiting > 0 ? (
                    <li>
                        <p className="font-medium">
                            {t('brokerage.brief.approvals', {
                                count: brief.approvals.waiting,
                                overdue: brief.approvals.overdue,
                            })}
                        </p>
                    </li>
                ) : null}
                {brief.ce_due ? (
                    <li>
                        <p className="font-medium">
                            {t('brokerage.brief.ceDue', {
                                count: brief.ce_due,
                            })}
                        </p>
                    </li>
                ) : null}
                <li>
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.brief.closings', {
                            count: brief.closings.count,
                            volume: f.money(brief.closings.volume),
                        })}
                    </p>
                </li>
            </ul>
            <div className="flex flex-wrap gap-2">
                <Button asChild variant="outline">
                    <a href={links.oversight}>
                        {t('brokerage.brief.openOversight')}
                    </a>
                </Button>
                {brief.approvals.waiting > 0 ? (
                    <Button asChild variant="outline">
                        <a href={links.approvals}>
                            {t('brokerage.brief.openApprovals')}
                        </a>
                    </Button>
                ) : null}
                {brief.ce_due ? (
                    <Button asChild variant="outline">
                        <a href={links.ce}>{t('brokerage.brief.openCe')}</a>
                    </Button>
                ) : null}
                <Button asChild variant="outline">
                    <a href={links.reports}>
                        {t('brokerage.brief.openReports')}
                    </a>
                </Button>
            </div>
        </section>
    );
}
