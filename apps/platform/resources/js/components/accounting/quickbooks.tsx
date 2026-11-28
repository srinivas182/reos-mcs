import { BookOpenCheck, Plug, Send } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/billing';
import { deleteJson, postJson, putJson } from '@/lib/content';
import type { AccountRole, QuickBooksBoard, QuickBooksRoutes } from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base';
const ROLES: AccountRole[] = [
    'clearing',
    'brokerage_income',
    'team_payable',
    'agent_payable',
    'payouts_expense',
    'payouts_clearing',
];

function useRoleLabel() {
    const { t } = useTranslation();

    return (role: AccountRole): string => {
        switch (role) {
            case 'clearing':
                return t('accounting.roles.clearing');
            case 'brokerage_income':
                return t('accounting.roles.brokerage_income');
            case 'team_payable':
                return t('accounting.roles.team_payable');
            case 'agent_payable':
                return t('accounting.roles.agent_payable');
            case 'payouts_expense':
                return t('accounting.roles.payouts_expense');
            case 'payouts_clearing':
                return t('accounting.roles.payouts_clearing');
        }
    };
}

/**
 * QuickBooks Online (B17.3, broker web journey: integrations): connect the
 * company, map the accounts once, see what's waiting, and export now.
 */
export function QuickBooksPanel({
    board,
    routes,
    onChanged,
    onRedirect = (url) => window.location.assign(url),
}: {
    board: QuickBooksBoard;
    routes: QuickBooksRoutes;
    onChanged: () => void;
    /** Sends the broker to Intuit to sign in. */
    onRedirect?: (url: string) => void;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const roleLabel = useRoleLabel();
    const [map, setMap] = useState<Record<string, string>>(
        board.connection?.accounts ?? {},
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const cash = (cents: number) => money(cents, i18n.language);

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
    const connection = board.connection;
    const pending =
        board.pending.split_sheets.length + board.pending.payouts.length;

    return (
        <div className="space-y-6">
            <section aria-labelledby={`${ids}-connect`} className={card}>
                <h2
                    id={`${ids}-connect`}
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Plug aria-hidden className="size-5" />
                    {t('accounting.title')}
                </h2>
                {connection === null || connection.status !== 'connected' ? (
                    <>
                        <p className="text-body text-muted-foreground">
                            {connection === null
                                ? t('accounting.connectHint')
                                : t('accounting.needsReconnect')}
                        </p>
                        <Button
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    const r = await postJson<{
                                        data: { url: string };
                                    }>(routes.connect);
                                    onRedirect(r.data.url);
                                })
                            }
                        >
                            {t('accounting.connect')}
                        </Button>
                    </>
                ) : (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-medium">
                                {t('accounting.company', {
                                    company: connection.company,
                                })}
                            </p>
                            {connection.mode === 'simulator' ? (
                                <p className="text-body text-muted-foreground">
                                    {t('accounting.simulated')}
                                </p>
                            ) : null}
                        </div>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    await deleteJson(routes.disconnect);
                                    toast.success(t('accounting.disconnected'));
                                    onChanged();
                                })
                            }
                        >
                            {t('accounting.disconnect')}
                        </Button>
                    </div>
                )}
            </section>

            {connection !== null && connection.status === 'connected' ? (
                <form
                    className={card}
                    aria-labelledby={`${ids}-map`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        void run(async () => {
                            await putJson(routes.accounts, { accounts: map });
                            toast.success(t('accounting.saved'));
                            onChanged();
                        });
                    }}
                >
                    <h2 id={`${ids}-map`} className="text-lg font-semibold">
                        {t('accounting.map')}
                    </h2>
                    <p className="text-body text-muted-foreground">
                        {t('accounting.mapHint')}
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        {ROLES.map((role) => (
                            <div key={role} className="space-y-2">
                                <Label htmlFor={`${ids}-${role}`}>
                                    {roleLabel(role)}
                                </Label>
                                <select
                                    id={`${ids}-${role}`}
                                    className={select}
                                    value={map[role] ?? ''}
                                    onChange={(e) =>
                                        setMap({
                                            ...map,
                                            [role]: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">
                                        {t('accounting.choose')}
                                    </option>
                                    {board.chart.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {`${a.name} (${a.type})`}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ))}
                    </div>
                    <Button
                        type="submit"
                        disabled={busy || ROLES.some((r) => !map[r])}
                    >
                        {t('accounting.save')}
                    </Button>
                </form>
            ) : null}

            <section aria-labelledby={`${ids}-pending`} className={card}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        id={`${ids}-pending`}
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <Send aria-hidden className="size-5" />
                        {t('accounting.pending')}
                    </h2>
                    {connection?.mapped && pending > 0 ? (
                        <Button
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    const r = await postJson<{
                                        data: {
                                            posted: number;
                                            failed: number;
                                        };
                                    }>(routes.export);
                                    toast.success(
                                        t('accounting.exportedResult', r.data),
                                    );
                                    onChanged();
                                })
                            }
                        >
                            {t('accounting.export')}
                        </Button>
                    ) : null}
                </div>
                {pending === 0 ? (
                    <EmptyState
                        icon={Send}
                        title={t('accounting.pendingEmpty')}
                        description={t('accounting.pendingEmptyHint')}
                    />
                ) : (
                    <ul className="space-y-2">
                        {board.pending.split_sheets.map((s) => (
                            <li key={s.id} className="text-body">
                                {t('accounting.sheetRow', {
                                    property: s.property,
                                    agent: s.agent,
                                    amount: cash(s.gross_cents),
                                })}
                            </li>
                        ))}
                        {board.pending.payouts.map((p) => (
                            <li key={p.id} className="text-body">
                                {t('accounting.payoutRow', {
                                    payee: p.payee,
                                    amount: cash(p.amount_cents),
                                })}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby={`${ids}-history`} className={card}>
                <h2
                    id={`${ids}-history`}
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <BookOpenCheck aria-hidden className="size-5" />
                    {t('accounting.history')}
                </h2>
                {board.exports.length === 0 ? (
                    <EmptyState
                        icon={BookOpenCheck}
                        title={t('accounting.historyEmpty')}
                        description={t('accounting.historyEmptyHint')}
                    />
                ) : (
                    <ul className="divide-y divide-border">
                        {board.exports.map((x) => (
                            <li
                                key={`${x.kind}-${x.subject}`}
                                className="flex flex-wrap justify-between gap-2 py-3 text-body"
                            >
                                <span>
                                    {x.kind === 'payout'
                                        ? t('accounting.kinds.payout')
                                        : t(
                                              'accounting.kinds.split_sheet',
                                          )}{' '}
                                    · {cash(x.amount_cents)}
                                </span>
                                <span
                                    className={
                                        x.status === 'posted'
                                            ? 'text-muted-foreground'
                                            : 'font-medium text-destructive'
                                    }
                                >
                                    {x.status === 'posted'
                                        ? t('accounting.statuses.posted', {
                                              ref: x.ref ?? '',
                                          })
                                        : t('accounting.statuses.failed', {
                                              error: x.error ?? '',
                                          })}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            <InputError message={error ?? undefined} />
        </div>
    );
}
