import { Send, Users } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type {
    AgentLevel,
    AgentsInAreaView,
    AreaAgent,
    RequestUsage,
    SentRequest,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 rounded-reos-md border border-input bg-background px-3 text-base';
const LEVELS: readonly AgentLevel[] = ['top', 'active', 'new'];
const STATUSES = [
    'pending',
    'accepted',
    'declined',
    'expired',
    'withdrawn',
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

function useDate() {
    const { i18n } = useTranslation();

    return (iso: string) =>
        new Date(
            iso.length === 10 ? `${iso}T12:00:00` : iso,
        ).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
}

function useLabels() {
    const { t } = useTranslation();

    return {
        level: (l: AgentLevel): string =>
            ({
                new: t('providers.agents.levels.new'),
                active: t('providers.agents.levels.active'),
                top: t('providers.agents.levels.top'),
            })[l],
        status: (s: string): string => {
            switch (STATUSES.find((x) => x === s)) {
                case 'pending':
                    return t('providers.agents.statuses.pending');
                case 'accepted':
                    return t('providers.agents.statuses.accepted');
                case 'declined':
                    return t('providers.agents.statuses.declined');
                case 'expired':
                    return t('providers.agents.statuses.expired');
                case 'withdrawn':
                    return t('providers.agents.statuses.withdrawn');
                default:
                    return s;
            }
        },
    };
}

function UsageBar({ usage }: { usage: RequestUsage }) {
    const { t } = useTranslation();
    const date = useDate();
    const percent =
        usage.limit > 0 ? Math.round((usage.used * 100) / usage.limit) : 100;

    return (
        <div className="space-y-2">
            <p className="text-base">
                {t('providers.agents.usage', {
                    used: usage.used,
                    limit: usage.limit,
                    date: date(usage.resets_on),
                })}
            </p>
            <div
                className="h-2 w-full max-w-sm overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={usage.limit}
                aria-valuenow={usage.used}
                aria-label={t('providers.agents.usageLeft', {
                    count: usage.left,
                })}
            >
                <div
                    className={cn(
                        'h-full',
                        usage.left === 0 ? 'bg-destructive' : 'bg-primary',
                    )}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

/**
 * Agents in my areas (B16.1, provider journey): REOS agents in the
 * provider's areas by level, filters, and Ask to connect within the
 * plan's monthly limit. First names only; contacts come when both agree.
 */
export function AgentsInArea({
    view,
    sent: initialSent,
    routes,
    onFilter,
}: {
    view: AgentsInAreaView;
    sent: SentRequest[];
    routes: { request: string; withdraw: (id: string) => string };
    onFilter: (filters: {
        level: AgentLevel | null;
        area: number | null;
    }) => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const date = useDate();
    const labels = useLabels();
    const [agents, setAgents] = useState(view.agents);
    const [usage, setUsage] = useState(view.usage);
    const [sent, setSent] = useState(initialSent);
    const [asking, setAsking] = useState<AreaAgent | null>(null);
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const send = async () => {
        if (!asking) {
            return;
        }

        setBusy(true);
        setErrors({});

        try {
            const res = await postJson<{
                data: { id: string; status: string; expires_at: string };
                usage: RequestUsage;
            }>(routes.request, { agent_id: asking.id, message: note || null });
            setUsage(res.usage);
            setAgents((list) =>
                list.map((a) =>
                    a.id === asking.id
                        ? {
                              ...a,
                              can_request: false,
                              request: {
                                  id: res.data.id,
                                  status: res.data.status,
                                  sent_at: new Date().toISOString(),
                              },
                          }
                        : a,
                ),
            );
            setSent((list) => [
                {
                    id: res.data.id,
                    agent: asking.name,
                    status: res.data.status,
                    message: note || null,
                    sent_at: new Date().toISOString(),
                    expires_at: res.data.expires_at,
                },
                ...list,
            ]);
            toast.success(t('providers.agents.sent', { name: asking.name }));
            setAsking(null);
            setNote('');
        } catch (e) {
            setErrors(errorsOf(e));
        } finally {
            setBusy(false);
        }
    };

    const withdraw = async (r: SentRequest) => {
        try {
            await postJson(routes.withdraw(r.id));
            setSent((list) =>
                list.map((x) =>
                    x.id === r.id ? { ...x, status: 'withdrawn' } : x,
                ),
            );
            toast.success(t('providers.agents.withdrawn'));
        } catch (e) {
            toast.error(Object.values(errorsOf(e))[0] ?? '');
        }
    };

    return (
        <div className="space-y-6">
            <section className={card} aria-labelledby={`${ids}-usage`}>
                <h2 id={`${ids}-usage`} className="sr-only">
                    {t('providers.agents.usageLeft', { count: usage.left })}
                </h2>
                <UsageBar usage={usage} />
                <p className="text-base text-muted-foreground">
                    {t('providers.agents.privacy')}
                </p>
            </section>

            <div className="flex flex-wrap items-end gap-4">
                <div className="space-y-2">
                    <Label htmlFor={`${ids}-level`}>
                        {t('providers.agents.filters.level')}
                    </Label>
                    <select
                        id={`${ids}-level`}
                        className={select}
                        value={view.filters.level ?? ''}
                        onChange={(e) =>
                            onFilter({
                                level:
                                    LEVELS.find((l) => l === e.target.value) ??
                                    null,
                                area: view.filters.area,
                            })
                        }
                    >
                        <option value="">
                            {t('providers.agents.filters.all')}
                        </option>
                        {LEVELS.map((l) => (
                            <option key={l} value={l}>
                                {labels.level(l)}
                            </option>
                        ))}
                    </select>
                </div>
                {view.areas.length > 1 ? (
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-area`}>
                            {t('providers.agents.filters.area')}
                        </Label>
                        <select
                            id={`${ids}-area`}
                            className={select}
                            value={view.filters.area ?? ''}
                            onChange={(e) =>
                                onFilter({
                                    level: view.filters.level,
                                    area:
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                })
                            }
                        >
                            <option value="">
                                {t('providers.agents.filters.allAreas')}
                            </option>
                            {view.areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null}
                <p className="pb-3 text-base text-muted-foreground">
                    {t('providers.agents.counts', view.counts)}
                </p>
            </div>

            {agents.length === 0 ? (
                <EmptyState
                    icon={Users}
                    title={t('providers.agents.empty')}
                    description={t('providers.agents.emptyHint')}
                />
            ) : (
                <section
                    className={card}
                    aria-label={t('providers.agents.title')}
                >
                    <ul className="divide-y divide-border">
                        {agents.map((a) => (
                            <li
                                key={a.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-4"
                            >
                                <div className="space-y-1">
                                    <p className="font-medium">
                                        {a.name}
                                        {a.brokerage ? (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {a.brokerage}
                                            </span>
                                        ) : null}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-2 text-base text-muted-foreground">
                                        <span
                                            className={cn(
                                                'rounded-full px-3 py-1 text-sm',
                                                a.level === 'top'
                                                    ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                    : 'bg-muted text-foreground',
                                            )}
                                        >
                                            {labels.level(a.level)}
                                        </span>
                                        {t('providers.agents.sides', {
                                            count: a.sides,
                                        })}
                                        {a.cities.length > 0
                                            ? ` · ${a.cities.join(', ')}`
                                            : ''}
                                    </p>
                                </div>
                                {a.request && !a.can_request ? (
                                    <span className="text-base text-muted-foreground">
                                        {labels.status(a.request.status)}
                                    </span>
                                ) : (
                                    <Button
                                        aria-label={t(
                                            'providers.agents.askFor',
                                            {
                                                name: a.name,
                                            },
                                        )}
                                        disabled={usage.left === 0}
                                        onClick={() => {
                                            setErrors({});
                                            setAsking(a);
                                        }}
                                    >
                                        <Send aria-hidden />
                                        {t('providers.agents.ask')}
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className={card} aria-labelledby={`${ids}-sent`}>
                <h2 id={`${ids}-sent`} className="text-title font-semibold">
                    {t('providers.agents.sentTitle')}
                </h2>
                {sent.length === 0 ? (
                    <div className="space-y-1">
                        <p className="font-medium">
                            {t('providers.agents.sentEmpty')}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {t('providers.agents.sentEmptyHint')}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y divide-border">
                        {sent.map((r) => (
                            <li
                                key={r.id}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <div>
                                    <p className="font-medium">
                                        {r.agent} · {labels.status(r.status)}
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        {r.status === 'pending'
                                            ? t('providers.agents.expires', {
                                                  date: date(r.expires_at),
                                              })
                                            : t('providers.agents.sentOn', {
                                                  date: date(r.sent_at),
                                              })}
                                    </p>
                                </div>
                                {r.status === 'pending' ? (
                                    <Button
                                        variant="outline"
                                        aria-label={t(
                                            'providers.agents.withdrawFor',
                                            { name: r.agent },
                                        )}
                                        onClick={() => void withdraw(r)}
                                    >
                                        {t('providers.agents.withdraw')}
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Dialog
                open={asking !== null}
                onOpenChange={(o) => (o ? null : setAsking(null))}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('providers.agents.dialog.title', {
                                name: asking?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('providers.agents.dialog.description', {
                                name: asking?.name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-note`}>
                            {t('providers.agents.dialog.note')}
                        </Label>
                        <Textarea
                            id={`${ids}-note`}
                            value={note}
                            maxLength={500}
                            onChange={(e) => setNote(e.target.value)}
                        />
                        <p className="text-base text-muted-foreground">
                            {t('providers.agents.dialog.noteHint')}
                        </p>
                        <InputError
                            message={
                                errors.message ??
                                errors.limit ??
                                errors.agent ??
                                errors.form
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setAsking(null)}
                        >
                            {t('providers.agents.dialog.cancel')}
                        </Button>
                        <Button onClick={() => void send()} disabled={busy}>
                            {t('providers.agents.dialog.send')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
