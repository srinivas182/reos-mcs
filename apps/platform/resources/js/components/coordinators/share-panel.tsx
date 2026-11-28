import { UserCheck } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { deleteJson, getJson, postJson, putJson } from '@/lib/content';
import type { DealCoordinators, SharePart, ShareRow, Switches } from './types';

export const PARTS: SharePart[] = ['dates', 'documents', 'parties', 'messages'];
/** Decision 4: documents and dates on, parties and messages off. */
export const DEFAULT_SWITCHES: Switches = {
    dates: true,
    documents: true,
    parties: false,
    messages: false,
};

/** Where the panel reads and changes a deal's coordinators. */
export type ShareRoutes = {
    list: string;
    share: string;
    update: (id: string) => string;
    remove: (id: string) => string;
};

export function usePartLabel() {
    const { t } = useTranslation();

    return (part: SharePart): string => {
        switch (part) {
            case 'dates':
                return t('coordinators.share.parts.dates');
            case 'documents':
                return t('coordinators.share.parts.documents');
            case 'parties':
                return t('coordinators.share.parts.parties');
            case 'messages':
                return t('coordinators.share.parts.messages');
        }
    };
}

function useEndedLabel() {
    const { t } = useTranslation();

    return (reason: string | null): string => {
        switch (reason) {
            case 'cancelled':
                return t('coordinators.share.ended.cancelled');
            case 'removed':
                return t('coordinators.share.ended.removed');
            default:
                return t('coordinators.share.ended.closed');
        }
    };
}

const select =
    'h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base';

/**
 * Share a deal with a transaction coordinator (B17.1, agent web journey):
 * a connected coordinator, switches for what they see, and access that
 * ends at closing. Shown in the deal's panel.
 */
export function SharePanel({
    routes,
    closed,
    initial = null,
}: {
    routes: ShareRoutes;
    /** The deal is closed or cancelled: nothing new can be shared. */
    closed: boolean;
    /** For stories and tests: skip loading. */
    initial?: DealCoordinators | null;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const partLabel = usePartLabel();
    const endedLabel = useEndedLabel();
    const [view, setView] = useState<DealCoordinators | null>(initial);
    const [chosen, setChosen] = useState<string>('');
    const [switches, setSwitches] = useState<Switches>(DEFAULT_SWITCHES);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = () =>
        getJson<{ data: DealCoordinators }>(routes.list)
            .then((r) => setView(r.data ?? { coordinators: [], shares: [] }))
            .catch(() => setView({ coordinators: [], shares: [] }));

    useEffect(() => {
        if (initial === null) {
            void load();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [routes.list]);

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        setError(null);
        try {
            await work();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    if (view === null) {
        return null;
    }

    const active = view.shares.filter((s) => s.status === 'active');
    const ended = view.shares.filter((s) => s.status !== 'active');
    const available = view.coordinators.filter(
        (c) => !active.some((s) => s.coordinator === c.name),
    );
    const update = (row: ShareRow, part: SharePart, on: boolean) =>
        run(async () => {
            const next = { ...row.switches, [part]: on };
            await putJson(routes.update(row.id), { switches: next });
            setView({
                ...view,
                shares: view.shares.map((s) =>
                    s.id === row.id ? { ...s, switches: next } : s,
                ),
            });
            toast.success(
                t('coordinators.share.saved', { name: row.coordinator }),
            );
        });

    return (
        <section aria-labelledby={`${ids}-title`} className="space-y-4">
            <div className="space-y-1">
                <h3 id={`${ids}-title`} className="font-semibold">
                    {t('coordinators.share.title')}
                </h3>
                <p className="text-body text-muted-foreground">
                    {t('coordinators.share.purpose')}
                </p>
            </div>

            {active.map((row) => (
                <div
                    key={row.id}
                    className="space-y-3 rounded-reos-md border border-border bg-card p-4"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="font-medium">{row.coordinator}</p>
                            <p className="text-body text-muted-foreground">
                                {t('coordinators.share.active')}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    await deleteJson(routes.remove(row.id));
                                    toast.success(
                                        t('coordinators.share.removed', {
                                            name: row.coordinator,
                                        }),
                                    );
                                    await load();
                                })
                            }
                        >
                            {t('coordinators.share.remove', {
                                name: row.coordinator,
                            })}
                        </Button>
                    </div>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {PARTS.map((part) => (
                            <li
                                key={part}
                                className="flex min-h-12 items-center justify-between gap-3"
                            >
                                <Label htmlFor={`${ids}-${row.id}-${part}`}>
                                    {partLabel(part)}
                                </Label>
                                <Switch
                                    id={`${ids}-${row.id}-${part}`}
                                    checked={row.switches[part]}
                                    disabled={busy}
                                    aria-label={t(
                                        'coordinators.share.partFor',
                                        {
                                            part: partLabel(part),
                                            name: row.coordinator,
                                        },
                                    )}
                                    onCheckedChange={(on) =>
                                        void update(row, part, on)
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                </div>
            ))}

            {closed ? (
                <p className="text-body text-muted-foreground">
                    {t('coordinators.share.closedNote')}
                </p>
            ) : view.coordinators.length === 0 ? (
                <EmptyState
                    icon={UserCheck}
                    title={t('coordinators.share.none')}
                    description={t('coordinators.share.noneHint')}
                />
            ) : available.length > 0 ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const coordinator = available.find(
                            (c) => String(c.id) === chosen,
                        );
                        if (coordinator === undefined) {
                            return;
                        }
                        void run(async () => {
                            await postJson(routes.share, {
                                coordinator_id: coordinator.id,
                                switches,
                            });
                            toast.success(
                                t('coordinators.share.shared', {
                                    name: coordinator.name,
                                }),
                            );
                            setChosen('');
                            setSwitches(DEFAULT_SWITCHES);
                            await load();
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-who`}>
                            {t('coordinators.share.coordinator')}
                        </Label>
                        <select
                            id={`${ids}-who`}
                            className={select}
                            value={chosen}
                            onChange={(e) => setChosen(e.target.value)}
                        >
                            <option value="">
                                {t('coordinators.share.choose')}
                            </option>
                            {available.map((c) => (
                                <option key={c.id} value={String(c.id)}>
                                    {c.business === c.name
                                        ? c.name
                                        : `${c.name} · ${c.business}`}
                                </option>
                            ))}
                        </select>
                    </div>
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {PARTS.map((part) => (
                            <li
                                key={part}
                                className="flex min-h-12 items-center justify-between gap-3"
                            >
                                <Label htmlFor={`${ids}-new-${part}`}>
                                    {partLabel(part)}
                                </Label>
                                <Switch
                                    id={`${ids}-new-${part}`}
                                    checked={switches[part]}
                                    onCheckedChange={(on) =>
                                        setSwitches({ ...switches, [part]: on })
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                    <Button type="submit" disabled={busy || chosen === ''}>
                        {t('coordinators.share.share')}
                    </Button>
                </form>
            ) : null}

            {ended.length > 0 ? (
                <ul className="space-y-1">
                    {ended.map((row) => (
                        <li
                            key={row.id}
                            className="text-body text-muted-foreground"
                        >
                            {row.coordinator} · {endedLabel(row.end_reason)}
                        </li>
                    ))}
                </ul>
            ) : null}
            <InputError message={error ?? undefined} />
        </section>
    );
}
