import { FileSignature, Inbox, Send } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { money } from '@/lib/billing';
import { postJson, putJson } from '@/lib/content';
import type {
    RoutingBoard,
    RoutingRules,
    SplitSheetRow,
    SplitsBoard,
    TeamLead,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 rounded-reos-md border border-input bg-background px-3 text-base';
const SOURCES = [
    'website',
    'zillow',
    'realtor_com',
    'open_house',
    'referral',
    'other',
] as const;

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

function useLabels() {
    const { t, i18n } = useTranslation();

    return {
        source: (s: string): string => {
            switch (SOURCES.find((x) => x === s)) {
                case 'website':
                    return t('teams.routing.sourceNames.website');
                case 'zillow':
                    return t('teams.routing.sourceNames.zillow');
                case 'realtor_com':
                    return t('teams.routing.sourceNames.realtor_com');
                case 'open_house':
                    return t('teams.routing.sourceNames.open_house');
                case 'referral':
                    return t('teams.routing.sourceNames.referral');
                default:
                    return t('teams.routing.sourceNames.other');
            }
        },
        lead: (l: TeamLead): string => {
            switch (l.status) {
                case 'claimed':
                    return t('teams.routing.statuses.claimed', {
                        agent: l.agent ?? '',
                    });
                case 'assigned':
                    return t('teams.routing.statuses.assigned', {
                        agent: l.agent ?? '',
                    });
                case 'leader':
                    return t('teams.routing.statuses.leader');
                default:
                    return t('teams.routing.statuses.offered');
            }
        },
        sheet: (s: SplitSheetRow): string =>
            s.status === 'signed'
                ? t('teams.splits.statuses.signed')
                : s.status === 'questioned'
                  ? t('teams.splits.statuses.questioned')
                  : t('teams.splits.statuses.sent'),
        date: (iso: string) =>
            new Date(
                iso.length === 10 ? `${iso}T12:00:00` : iso,
            ).toLocaleDateString(i18n.language, { dateStyle: 'medium' }),
        time: (iso: string) =>
            new Date(iso).toLocaleTimeString(i18n.language, {
                timeStyle: 'short',
            }),
        money: (cents: number) => money(cents, i18n.language),
    };
}

/**
 * Lead routing (A17.3, team leader screens 28–29): rules, the rotation
 * and recent leads, with Add a lead.
 */
export function LeadRoutingPanel({
    board: initial,
    routes,
}: {
    board: RoutingBoard;
    routes: { rules: string; member: (id: number) => string; lead: string };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const labels = useLabels();
    const [board, setBoard] = useState(initial);
    const [rules, setRules] = useState<RoutingRules>(initial.rules);
    const [lead, setLead] = useState({
        name: '',
        email: '',
        phone: '',
        source: 'website',
        language: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const save = async () => {
        setErrors({});

        try {
            setBoard(
                (await putJson<{ data: RoutingBoard }>(routes.rules, rules))
                    .data,
            );
            toast.success(t('teams.routing.saved'));
        } catch (e) {
            setErrors(errorsOf(e));
        }
    };
    const toggle = async (id: number, active: boolean, languages: string[]) => {
        setBoard(
            (
                await putJson<{ data: RoutingBoard }>(routes.member(id), {
                    active,
                    languages,
                })
            ).data,
        );
    };
    const add = async () => {
        setErrors({});

        try {
            await postJson(routes.lead, {
                ...lead,
                email: lead.email || null,
                phone: lead.phone || null,
                language: lead.language || null,
            });
            setLead({ ...lead, name: '', email: '', phone: '' });
            window.location.reload();
        } catch (e) {
            setErrors(errorsOf(e));
        }
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-rules`}>
                <h2 id={`${ids}-rules`} className="text-title font-semibold">
                    {t('teams.routing.title')}
                </h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-method`}>
                            {t('teams.routing.method')}
                        </Label>
                        <select
                            id={`${ids}-method`}
                            className={select}
                            value={rules.method}
                            onChange={(e) =>
                                setRules({
                                    ...rules,
                                    method:
                                        e.target.value === 'round_robin'
                                            ? 'round_robin'
                                            : 'first_to_claim',
                                })
                            }
                        >
                            <option value="first_to_claim">
                                {t('teams.routing.methods.first_to_claim')}
                            </option>
                            <option value="round_robin">
                                {t('teams.routing.methods.round_robin')}
                            </option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-minutes`}>
                            {t('teams.routing.claimMinutes')}
                        </Label>
                        <Input
                            id={`${ids}-minutes`}
                            type="number"
                            min={1}
                            max={30}
                            value={rules.claim_minutes}
                            onChange={(e) =>
                                setRules({
                                    ...rules,
                                    claim_minutes: Number(e.target.value),
                                })
                            }
                        />
                        <InputError message={errors.claim_minutes} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-fallback`}>
                            {t('teams.routing.fallback')}
                        </Label>
                        <select
                            id={`${ids}-fallback`}
                            className={select}
                            value={rules.fallback}
                            onChange={(e) =>
                                setRules({
                                    ...rules,
                                    fallback:
                                        e.target.value === 'leader'
                                            ? 'leader'
                                            : 'round_robin',
                                })
                            }
                        >
                            <option value="round_robin">
                                {t('teams.routing.fallbacks.round_robin')}
                            </option>
                            <option value="leader">
                                {t('teams.routing.fallbacks.leader')}
                            </option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-off-start`}>
                            {t('teams.routing.offStart')}
                        </Label>
                        <Input
                            id={`${ids}-off-start`}
                            type="time"
                            value={rules.off_start}
                            onChange={(e) =>
                                setRules({
                                    ...rules,
                                    off_start: e.target.value,
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-off-end`}>
                            {t('teams.routing.offEnd')}
                        </Label>
                        <Input
                            id={`${ids}-off-end`}
                            type="time"
                            value={rules.off_end}
                            onChange={(e) =>
                                setRules({ ...rules, off_end: e.target.value })
                            }
                        />
                    </div>
                </div>
                <fieldset className="space-y-2">
                    <legend className="text-base font-medium">
                        {t('teams.routing.sources')}
                    </legend>
                    <div className="flex flex-wrap gap-4">
                        {SOURCES.map((s) => (
                            <label
                                key={s}
                                htmlFor={`${ids}-src-${s}`}
                                className="flex min-h-12 items-center gap-2 text-base"
                            >
                                <Checkbox
                                    id={`${ids}-src-${s}`}
                                    checked={rules.sources.includes(s)}
                                    onCheckedChange={(on) =>
                                        setRules({
                                            ...rules,
                                            sources:
                                                on === true
                                                    ? [...rules.sources, s]
                                                    : rules.sources.filter(
                                                          (x) => x !== s,
                                                      ),
                                        })
                                    }
                                />
                                {labels.source(s)}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <Button onClick={() => void save()}>
                    {t('teams.routing.save')}
                </Button>
            </section>

            <section className={card} aria-labelledby={`${ids}-rotation`}>
                <h2 id={`${ids}-rotation`} className="text-title font-semibold">
                    {t('teams.routing.rotation')}
                </h2>
                <ul className="divide-y divide-border">
                    {board.members.map((m) => (
                        <li
                            key={m.id}
                            className="flex items-center justify-between gap-3 py-3"
                        >
                            <Label
                                htmlFor={`${ids}-m-${m.id}`}
                                className="text-base"
                            >
                                {m.name}{' '}
                                <span className="text-muted-foreground">
                                    · {m.languages.join(', ').toUpperCase()}
                                </span>
                            </Label>
                            <Switch
                                id={`${ids}-m-${m.id}`}
                                aria-label={t('teams.routing.inRotation', {
                                    name: m.name,
                                })}
                                checked={m.active}
                                onCheckedChange={(on) =>
                                    void toggle(m.id, on, m.languages)
                                }
                            />
                        </li>
                    ))}
                </ul>
            </section>

            <section className={card} aria-labelledby={`${ids}-add`}>
                <h2 id={`${ids}-add`} className="text-title font-semibold">
                    {t('teams.routing.addLead')}
                </h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-lead-name`}>
                            {t('teams.routing.leadName')}
                        </Label>
                        <Input
                            id={`${ids}-lead-name`}
                            value={lead.name}
                            maxLength={160}
                            onChange={(e) =>
                                setLead({ ...lead, name: e.target.value })
                            }
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-lead-email`}>
                            {t('teams.routing.leadEmail')}
                        </Label>
                        <Input
                            id={`${ids}-lead-email`}
                            type="email"
                            value={lead.email}
                            onChange={(e) =>
                                setLead({ ...lead, email: e.target.value })
                            }
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-lead-source`}>
                            {t('teams.routing.leadSource')}
                        </Label>
                        <select
                            id={`${ids}-lead-source`}
                            className={select}
                            value={lead.source}
                            onChange={(e) =>
                                setLead({ ...lead, source: e.target.value })
                            }
                        >
                            {SOURCES.map((s) => (
                                <option key={s} value={s}>
                                    {labels.source(s)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-lead-lang`}>
                            {t('teams.routing.leadLanguage')}
                        </Label>
                        <select
                            id={`${ids}-lead-lang`}
                            className={select}
                            value={lead.language}
                            onChange={(e) =>
                                setLead({ ...lead, language: e.target.value })
                            }
                        >
                            <option value="">—</option>
                            <option value="en">English</option>
                            <option value="es">Español</option>
                        </select>
                    </div>
                </div>
                <Button
                    disabled={lead.name.trim() === ''}
                    onClick={() => void add()}
                >
                    <Send aria-hidden />
                    {t('teams.routing.route')}
                </Button>
            </section>

            <section className={card} aria-labelledby={`${ids}-leads`}>
                <h2 id={`${ids}-leads`} className="text-title font-semibold">
                    {t('teams.routing.leads')}
                </h2>
                {board.leads.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={t('teams.routing.leadsEmpty')}
                        description={t('teams.routing.leadsEmptyHint')}
                        className="border-0"
                    />
                ) : (
                    <ul className="divide-y divide-border">
                        {board.leads.map((l) => (
                            <li key={l.id} className="py-3">
                                <p className="font-medium">
                                    {l.name}{' '}
                                    <span className="text-muted-foreground">
                                        · {labels.source(l.source)}
                                    </span>
                                </p>
                                <p className="text-base text-muted-foreground">
                                    {labels.lead(l)}
                                    {l.auto_reply
                                        ? ` · ${t('teams.routing.offHoursReply')}`
                                        : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

/**
 * Split sheets for the team leader (A17.3, screen 30): the team agreement
 * and New split sheet, with each sheet's status.
 */
export function SplitSheetsPanel({
    board: initial,
    routes,
}: {
    board: SplitsBoard;
    routes: { agreement: string; send: string };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const labels = useLabels();
    const [agreement, setAgreement] = useState(initial.agreement);
    const [sheets] = useState(initial.sheets);
    const [form, setForm] = useState({
        agent_id: initial.agents[0]?.id ?? 0,
        property: '',
        closing_on: '',
        gross: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});

    const saveAgreement = async () => {
        setErrors({});

        try {
            setAgreement(
                (
                    await putJson<{ data: typeof agreement }>(
                        routes.agreement,
                        {
                            brokerage_percent: agreement.brokerage_percent,
                            team_percent: agreement.team_percent,
                        },
                    )
                ).data,
            );
        } catch (e) {
            setErrors(errorsOf(e));
        }
    };
    const send = async () => {
        setErrors({});

        try {
            await postJson(routes.send, {
                agent_id: form.agent_id,
                property: form.property,
                closing_on: form.closing_on,
                gross_cents: Math.round(Number(form.gross) * 100),
            });
            window.location.reload();
        } catch (e) {
            setErrors(errorsOf(e));
        }
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-agreement`}>
                <h2
                    id={`${ids}-agreement`}
                    className="text-title font-semibold"
                >
                    {t('teams.splits.agreement')}
                </h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-bp`}>
                            {t('teams.splits.brokeragePercent')}
                        </Label>
                        <Input
                            id={`${ids}-bp`}
                            type="number"
                            min={0}
                            max={100}
                            value={agreement.brokerage_percent}
                            onChange={(e) =>
                                setAgreement({
                                    ...agreement,
                                    brokerage_percent: Number(e.target.value),
                                })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-tp`}>
                            {t('teams.splits.teamPercent')}
                        </Label>
                        <Input
                            id={`${ids}-tp`}
                            type="number"
                            min={0}
                            max={100}
                            value={agreement.team_percent}
                            onChange={(e) =>
                                setAgreement({
                                    ...agreement,
                                    team_percent: Number(e.target.value),
                                })
                            }
                        />
                        <InputError message={errors.team_percent} />
                    </div>
                </div>
                <p className="text-base text-muted-foreground">
                    {t('teams.splits.agentPercent', {
                        percent: Math.max(
                            0,
                            100 -
                                agreement.brokerage_percent -
                                agreement.team_percent,
                        ),
                    })}
                </p>
                <Button variant="outline" onClick={() => void saveAgreement()}>
                    {t('teams.splits.saveAgreement')}
                </Button>
            </section>

            <section className={card} aria-labelledby={`${ids}-new`}>
                <h2 id={`${ids}-new`} className="text-title font-semibold">
                    {t('teams.splits.new')}
                </h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-agent`}>
                            {t('teams.splits.agent')}
                        </Label>
                        <select
                            id={`${ids}-agent`}
                            className={select}
                            value={form.agent_id}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    agent_id: Number(e.target.value),
                                })
                            }
                        >
                            {initial.agents.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-prop`}>
                            {t('teams.splits.property')}
                        </Label>
                        <Input
                            id={`${ids}-prop`}
                            value={form.property}
                            maxLength={200}
                            onChange={(e) =>
                                setForm({ ...form, property: e.target.value })
                            }
                        />
                        <InputError message={errors.property} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-close`}>
                            {t('teams.splits.closingOn')}
                        </Label>
                        <Input
                            id={`${ids}-close`}
                            type="date"
                            value={form.closing_on}
                            onChange={(e) =>
                                setForm({ ...form, closing_on: e.target.value })
                            }
                        />
                        <InputError message={errors.closing_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-gross`}>
                            {t('teams.splits.gross')}
                        </Label>
                        <Input
                            id={`${ids}-gross`}
                            type="number"
                            min={1}
                            value={form.gross}
                            onChange={(e) =>
                                setForm({ ...form, gross: e.target.value })
                            }
                        />
                        <InputError message={errors.gross_cents} />
                    </div>
                </div>
                <Button
                    disabled={
                        form.property.trim() === '' ||
                        form.closing_on === '' ||
                        form.gross === ''
                    }
                    onClick={() => void send()}
                >
                    <FileSignature aria-hidden />
                    {t('teams.splits.send')}
                </Button>
            </section>

            <SheetList sheets={sheets} labels={labels} />
        </div>
    );
}

function SheetList({
    sheets,
    labels,
    actions,
}: {
    sheets: SplitSheetRow[];
    labels: ReturnType<typeof useLabels>;
    actions?: (s: SplitSheetRow) => React.ReactNode;
}) {
    const { t } = useTranslation();
    const ids = useId();

    return (
        <section className={card} aria-labelledby={`${ids}-sheets`}>
            <h2 id={`${ids}-sheets`} className="text-title font-semibold">
                {t('teams.splits.title')}
            </h2>
            {sheets.length === 0 ? (
                <EmptyState
                    icon={FileSignature}
                    title={t('teams.splits.empty')}
                    description={t('teams.splits.emptyHint')}
                    className="border-0"
                />
            ) : (
                <ul className="divide-y divide-border">
                    {sheets.map((s) => (
                        <li key={s.id} className="space-y-2 py-3">
                            <p className="font-medium">
                                {s.agent} ·{' '}
                                {t('teams.splits.line', {
                                    property: s.property,
                                    date: labels.date(s.closing_on),
                                    gross: labels.money(s.gross),
                                })}
                            </p>
                            <p className="text-base text-muted-foreground">
                                {t('teams.splits.shares', {
                                    brokerage: labels.money(s.brokerage),
                                    team: labels.money(s.team),
                                    agent: labels.money(s.agent_share),
                                })}{' '}
                                · {labels.sheet(s)}
                                {s.exported_at
                                    ? ` · ${t('teams.splits.exported')}`
                                    : ''}
                            </p>
                            {actions ? actions(s) : null}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * The agent's team leads (A17.3): leads to claim before the time runs
 * out, and split sheets to sign or ask about.
 */
export function TeamLeads({
    offered: initialOffered,
    splits: initialSplits,
    routes,
}: {
    offered: TeamLead[];
    splits: SplitSheetRow[];
    routes: {
        claim: (id: string) => string;
        sign: (id: string) => string;
        question: (id: string) => string;
    };
}) {
    const { t } = useTranslation();
    const ids = useId();
    const labels = useLabels();
    const [offered, setOffered] = useState(initialOffered);
    const [splits, setSplits] = useState(initialSplits);
    const [asking, setAsking] = useState<string | null>(null);
    const [question, setQuestion] = useState('');

    const claim = async (l: TeamLead) => {
        try {
            await postJson(routes.claim(l.id));
            toast.success(t('teams.routing.claimed', { name: l.name }));
        } catch (e) {
            toast.error(Object.values(errorsOf(e))[0] ?? '');
        }

        setOffered((list) => list.filter((x) => x.id !== l.id));
    };
    const sign = async (s: SplitSheetRow) => {
        await postJson(routes.sign(s.id));
        setSplits((list) =>
            list.map((x) => (x.id === s.id ? { ...x, status: 'signed' } : x)),
        );
        toast.success(t('teams.splits.signed'));
    };
    const ask = async (s: SplitSheetRow) => {
        const res = await postJson<{ conversation: string }>(
            routes.question(s.id),
            { text: question },
        );
        setSplits((list) =>
            list.map((x) =>
                x.id === s.id ? { ...x, status: 'questioned' } : x,
            ),
        );
        setAsking(null);
        setQuestion('');
        window.location.href = `/messages?c=${res.conversation}`;
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-offered`}>
                <h2 id={`${ids}-offered`} className="text-title font-semibold">
                    {t('teams.routing.offered')}
                </h2>
                {offered.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={t('teams.routing.offeredEmpty')}
                        description={t('teams.routing.offeredEmptyHint')}
                        className="border-0"
                    />
                ) : (
                    <ul className="divide-y divide-border">
                        {offered.map((l) => (
                            <li
                                key={l.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <div>
                                    <p className="font-medium">
                                        {l.name}{' '}
                                        <span className="text-muted-foreground">
                                            · {labels.source(l.source)}
                                        </span>
                                    </p>
                                    {l.claim_by ? (
                                        <p className="text-base text-muted-foreground">
                                            {t('teams.routing.claimBy', {
                                                time: labels.time(l.claim_by),
                                            })}
                                        </p>
                                    ) : null}
                                </div>
                                <Button
                                    aria-label={t('teams.routing.claimFor', {
                                        name: l.name,
                                    })}
                                    onClick={() => void claim(l)}
                                >
                                    {t('teams.routing.claim')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            <SheetList
                sheets={splits}
                labels={labels}
                actions={(s) =>
                    s.status === 'signed' ? null : asking === s.id ? (
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-q-${s.id}`}>
                                {t('teams.splits.questionLabel')}
                            </Label>
                            <Input
                                id={`${ids}-q-${s.id}`}
                                value={question}
                                maxLength={1000}
                                onChange={(e) => setQuestion(e.target.value)}
                            />
                            <Button
                                variant="outline"
                                disabled={question.trim() === ''}
                                onClick={() => void ask(s)}
                            >
                                {t('teams.splits.ask')}
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                aria-label={t('teams.splits.signFor', {
                                    property: s.property,
                                })}
                                onClick={() => void sign(s)}
                            >
                                {t('teams.splits.sign')}
                            </Button>
                            <Button
                                variant="outline"
                                aria-label={t('teams.splits.askFor', {
                                    property: s.property,
                                })}
                                onClick={() => setAsking(s.id)}
                            >
                                {t('teams.splits.ask')}
                            </Button>
                        </div>
                    )
                }
            />
        </div>
    );
}
