import { BellRing, GraduationCap, UserMinus, Users } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type { CeStatus, CeTable, SponsorshipBoard } from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 rounded-reos-md border border-input bg-background px-3 text-base';

function errorsOf(e: unknown): Record<string, string> {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        return Object.fromEntries(
            Object.entries(
                (e as { errors: Record<string, string[] | string> }).errors,
            ).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : v]),
        );
    }

    return { form: e instanceof Error ? e.message : String(e) };
}

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(
            iso.length === 10 ? `${iso}T12:00:00` : iso,
        ).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
}

function StatusChip({ status }: { status: CeStatus }) {
    const { t } = useTranslation();
    const tone =
        status === 'overdue' || status === 'behind'
            ? 'bg-reos-tone-danger-bg text-reos-tone-danger-fg'
            : status === 'due'
              ? 'bg-reos-tone-warning-bg text-reos-tone-warning-fg'
              : status === 'done'
                ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                : 'bg-muted';
    const label: Record<CeStatus, string> = {
        done: t('brokerage.ce.statuses.done'),
        on_track: t('brokerage.ce.statuses.on_track'),
        due: t('brokerage.ce.statuses.due'),
        behind: t('brokerage.ce.statuses.behind'),
        overdue: t('brokerage.ce.statuses.overdue'),
        no_licence: t('brokerage.ce.statuses.no_licence'),
    };

    return (
        <span className={cn('rounded-full px-3 py-1 text-sm', tone)}>
            {label[status]}
        </span>
    );
}

/**
 * CE compliance (A16.2): every agent's renewal, hours left and SAE, with
 * Remind all due agents in one action and course assignment.
 */
export function CeCompliance({
    table: initial,
    routes,
}: {
    table: CeTable;
    routes: { remind: string; assign: string };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const [table, setTable] = useState(initial);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [course, setCourse] = useState(String(initial.courses[0]?.id ?? ''));
    const [who, setWho] = useState<'due' | 'selected'>('due');
    const [chosen, setChosen] = useState<number[]>([]);
    const [dueOn, setDueOn] = useState('');

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-base text-muted-foreground">
                    {t('brokerage.ce.counts', {
                        due: table.counts.due,
                        agents: table.counts.agents,
                        done: table.counts.done,
                    })}
                </p>
                <Button
                    type="button"
                    disabled={busy || table.counts.due === 0}
                    onClick={() => {
                        setBusy(true);
                        postJson<{
                            data: {
                                sent: number;
                                skipped: number;
                                table: CeTable;
                            };
                        }>(routes.remind)
                            .then((res) => {
                                setTable(res.data.table);
                                toast.success(
                                    [
                                        res.data.sent > 0
                                            ? t('brokerage.ce.reminded', {
                                                  count: res.data.sent,
                                              })
                                            : t('brokerage.ce.remindedNone'),
                                        res.data.skipped > 0
                                            ? t('brokerage.ce.skipped', {
                                                  count: res.data.skipped,
                                              })
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' '),
                                );
                            })
                            .catch((err: unknown) => setErrors(errorsOf(err)))
                            .finally(() => setBusy(false));
                    }}
                >
                    <BellRing aria-hidden className="size-5" />
                    {t('brokerage.ce.remindAll')}
                </Button>
            </div>

            {table.rows.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('brokerage.ce.emptyTitle')}
                    description={t('brokerage.ce.empty')}
                />
            ) : (
                <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                    <table className="w-full text-left text-base">
                        <thead className="border-b border-border text-muted-foreground">
                            <tr>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.agent')}
                                </th>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.status')}
                                </th>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.renewal')}
                                </th>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.hours')}
                                </th>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.assigned')}
                                </th>
                                <th scope="col" className="p-3 font-medium">
                                    {t('brokerage.ce.columns.reminded')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {table.rows.map((r) => (
                                <tr key={r.id}>
                                    <th scope="row" className="p-3 font-medium">
                                        {r.name}
                                        {r.team ? (
                                            <span className="block text-sm font-normal text-muted-foreground">
                                                {r.team}
                                            </span>
                                        ) : null}
                                    </th>
                                    <td className="p-3">
                                        <StatusChip status={r.status} />
                                        {r.sae ? (
                                            <span className="ml-2 text-sm text-muted-foreground">
                                                {t('brokerage.ce.sae')}
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="p-3">
                                        {r.due_on ? date(r.due_on) : '—'}
                                    </td>
                                    <td className="p-3">
                                        {r.status === 'no_licence'
                                            ? '—'
                                            : t('brokerage.ce.hours', {
                                                  left: r.hours_left,
                                                  required: r.hours_required,
                                              })}
                                    </td>
                                    <td className="p-3">
                                        {r.assignments.length === 0
                                            ? '—'
                                            : r.assignments.map((a) => (
                                                  <span
                                                      key={a.course}
                                                      className="block"
                                                  >
                                                      {a.course}
                                                      {a.done
                                                          ? ` · ${t('brokerage.ce.assign.done')}`
                                                          : a.due_on
                                                            ? ` · ${date(a.due_on)}`
                                                            : ''}
                                                  </span>
                                              ))}
                                    </td>
                                    <td className="p-3">
                                        {r.reminded_at
                                            ? date(r.reminded_at)
                                            : t('brokerage.ce.never')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <section aria-labelledby={`${ids}-assign`} className={card}>
                <h2
                    id={`${ids}-assign`}
                    className="flex items-center gap-2 font-semibold"
                >
                    <GraduationCap aria-hidden className="size-5" />
                    {t('brokerage.ce.assign.title')}
                </h2>
                {table.courses.length === 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('brokerage.ce.assign.noCourses')}
                    </p>
                ) : (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            setBusy(true);
                            setErrors({});
                            postJson<{
                                data: { assigned: number; table: CeTable };
                            }>(routes.assign, {
                                course_id: Number(course),
                                ...(who === 'due'
                                    ? { due: true }
                                    : { agents: chosen }),
                                due_on: dueOn === '' ? null : dueOn,
                            })
                                .then((res) => {
                                    setTable(res.data.table);
                                    toast.success(
                                        t('brokerage.ce.assign.assigned', {
                                            count: res.data.assigned,
                                        }),
                                    );
                                })
                                .catch((err: unknown) =>
                                    setErrors(errorsOf(err)),
                                )
                                .finally(() => setBusy(false));
                        }}
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-course`}>
                                    {t('brokerage.ce.assign.course')}
                                </Label>
                                <select
                                    id={`${ids}-course`}
                                    className={select}
                                    value={course}
                                    onChange={(e) => setCourse(e.target.value)}
                                >
                                    {table.courses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.title}
                                            {c.hours ? ` (${c.hours})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-who`}>
                                    {t('brokerage.ce.assign.who')}
                                </Label>
                                <select
                                    id={`${ids}-who`}
                                    className={select}
                                    value={who}
                                    onChange={(e) =>
                                        setWho(
                                            e.target.value === 'selected'
                                                ? 'selected'
                                                : 'due',
                                        )
                                    }
                                >
                                    <option value="due">
                                        {t('brokerage.ce.assign.due')}
                                    </option>
                                    <option value="selected">
                                        {t('brokerage.ce.assign.selected')}
                                    </option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-due-on`}>
                                    {t('brokerage.ce.assign.dueOn')}
                                </Label>
                                <Input
                                    id={`${ids}-due-on`}
                                    type="date"
                                    value={dueOn}
                                    onChange={(e) => setDueOn(e.target.value)}
                                />
                            </div>
                        </div>
                        {who === 'selected' ? (
                            <fieldset className="grid gap-2">
                                <legend className="font-medium">
                                    {t('brokerage.ce.assign.pick')}
                                </legend>
                                <div className="flex flex-wrap gap-2">
                                    {table.rows.map((r) => (
                                        <label
                                            key={r.id}
                                            className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3 text-base"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={chosen.includes(r.id)}
                                                onChange={(e) =>
                                                    setChosen(
                                                        e.target.checked
                                                            ? [...chosen, r.id]
                                                            : chosen.filter(
                                                                  (c) =>
                                                                      c !==
                                                                      r.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            {r.name}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        ) : null}
                        <InputError
                            message={
                                errors.course ??
                                errors.agents ??
                                errors.due_on ??
                                errors.form
                            }
                        />
                        <Button type="submit" variant="outline" disabled={busy}>
                            {t('brokerage.ce.assign.submit')}
                        </Button>
                    </form>
                )}
            </section>
        </div>
    );
}

/** Sponsorship (A16.2): joins and releases with TREC's confirmation, and releasing an agent with a handover of open deals. */
export function Sponsorships({
    board: initial,
    route,
}: {
    board: SponsorshipBoard;
    route: string;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const [board, setBoard] = useState(initial);
    const [agent, setAgent] = useState(String(initial.agents[0]?.id ?? ''));
    const [effective, setEffective] = useState(
        new Date().toISOString().slice(0, 10),
    );
    const [handover, setHandover] = useState('');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const selected = board.agents.find((a) => String(a.id) === agent);
    const statusLabel = (
        s: SponsorshipBoard['changes'][number]['status'],
        kind: 'join' | 'release',
    ) =>
        s === 'confirmed'
            ? t('brokerage.sponsorship.statuses.confirmed')
            : kind === 'join' || s === 'done'
              ? t('brokerage.sponsorship.statuses.done')
              : t('brokerage.sponsorship.statuses.recorded');

    return (
        <section aria-labelledby={`${ids}-title`} className={card}>
            <div className="space-y-1">
                <h2 id={`${ids}-title`} className="font-semibold">
                    {t('brokerage.sponsorship.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('brokerage.sponsorship.purpose')}
                </p>
                {board.trec_number === null ? (
                    <p className="text-base text-reos-tone-warning-fg">
                        {t('brokerage.sponsorship.noTrec')}
                    </p>
                ) : null}
            </div>
            {board.changes.length === 0 ? (
                <EmptyState
                    icon={UserMinus}
                    title={t('brokerage.sponsorship.emptyTitle')}
                    description={t('brokerage.sponsorship.empty')}
                />
            ) : (
                <ul className="divide-y divide-border">
                    {board.changes.map((c) => (
                        <li
                            key={c.id}
                            className="flex flex-wrap items-center justify-between gap-2 py-3 text-base"
                        >
                            <span>
                                {t('brokerage.sponsorship.row', {
                                    kind:
                                        c.kind === 'join'
                                            ? t(
                                                  'brokerage.sponsorship.kinds.join',
                                              )
                                            : t(
                                                  'brokerage.sponsorship.kinds.release',
                                              ),
                                    agent: c.agent,
                                    date: date(c.effective_on),
                                })}
                                {c.handover_to ? (
                                    <span className="block text-muted-foreground">
                                        {t('brokerage.sponsorship.handedOver', {
                                            count: c.deals,
                                            to: c.handover_to,
                                        })}
                                    </span>
                                ) : null}
                            </span>
                            <span className="rounded-full bg-muted px-3 py-1 text-sm">
                                {statusLabel(c.status, c.kind)}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {board.agents.length > 0 ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setBusy(true);
                        setErrors({});
                        postJson<{ data: SponsorshipBoard }>(route, {
                            agent_id: Number(agent),
                            effective_on: effective,
                            handover_to:
                                handover === '' ? null : Number(handover),
                        })
                            .then((res) => {
                                setBoard(res.data);
                                toast.success(
                                    t('brokerage.sponsorship.released', {
                                        agent: selected?.name ?? '',
                                        date: date(effective),
                                    }),
                                );
                            })
                            .catch((err: unknown) => setErrors(errorsOf(err)))
                            .finally(() => setBusy(false));
                    }}
                >
                    <h3 className="font-medium">
                        {t('brokerage.sponsorship.release')}
                    </h3>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`${ids}-agent`}>
                                {t('brokerage.sponsorship.agent')}
                            </Label>
                            <select
                                id={`${ids}-agent`}
                                className={select}
                                value={agent}
                                onChange={(e) => setAgent(e.target.value)}
                            >
                                {board.agents.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.name} ·{' '}
                                        {a.open_deals === 0
                                            ? t('brokerage.sponsorship.noDeals')
                                            : t(
                                                  'brokerage.sponsorship.openDeals',
                                                  { count: a.open_deals },
                                              )}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`${ids}-date`}>
                                {t('brokerage.sponsorship.date')}
                            </Label>
                            <Input
                                id={`${ids}-date`}
                                type="date"
                                value={effective}
                                onChange={(e) => setEffective(e.target.value)}
                            />
                        </div>
                        {selected && selected.open_deals > 0 ? (
                            <div className="grid gap-2">
                                <Label htmlFor={`${ids}-handover`}>
                                    {t('brokerage.sponsorship.handover')}
                                </Label>
                                <select
                                    id={`${ids}-handover`}
                                    className={select}
                                    value={handover}
                                    onChange={(e) =>
                                        setHandover(e.target.value)
                                    }
                                >
                                    <option value="">—</option>
                                    {board.handover
                                        .filter((p) => String(p.id) !== agent)
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                </select>
                            </div>
                        ) : null}
                    </div>
                    <InputError
                        message={
                            errors.handover_to ??
                            errors.effective_on ??
                            errors.agent ??
                            errors.form
                        }
                    />
                    <Button type="submit" variant="outline" disabled={busy}>
                        {t('brokerage.sponsorship.submit')}
                    </Button>
                </form>
            ) : null}
        </section>
    );
}
