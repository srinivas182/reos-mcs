import { BadgeCheck, Building2 } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/content';
import { cn } from '@/lib/utils';
import type { BrokerConfirmed, BrokerLookup, BrokerRoleChoice } from './types';

const ROLES: BrokerRoleChoice[] = [
    'designated_broker',
    'office_admin',
    'team_leader',
];

/**
 * Verify your brokerage (A15.1a, broker journey screen 2): the broker
 * license is checked against TREC's records, the person picks the brokerage
 * they're the designated broker of and their role. A designated broker
 * continues to the plan; an office admin or team leader is told to ask the
 * designated broker for an invite.
 */
export function VerifyBrokerage({
    routes,
    onVerified,
}: {
    routes: { lookup: string; confirm: string };
    onVerified: (confirmed: BrokerConfirmed) => void;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [licence, setLicence] = useState('');
    const [found, setFound] = useState<BrokerLookup | null>(null);
    const [brokerage, setBrokerage] = useState<string | null>(null);
    const [role, setRole] = useState<BrokerRoleChoice>('designated_broker');
    const [askBroker, setAskBroker] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const day = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(
            new Date(`${iso}T12:00:00`),
        );

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

    const chosen = found?.brokerages.find((b) => b.number === brokerage);

    return (
        <section
            aria-labelledby={`${ids}-title`}
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="space-y-1">
                <h2 id={`${ids}-title`} className="text-lg font-semibold">
                    {t('brokerage.verify.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('brokerage.verify.purpose')}
                </p>
            </header>

            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    void run(async () => {
                        const next = (
                            await postJson<{ data: BrokerLookup }>(
                                routes.lookup,
                                { licence },
                            )
                        ).data;
                        setFound(next);
                        setAskBroker(null);
                        setBrokerage(
                            next.brokerages.find((b) => !b.on_reos)?.number ??
                                null,
                        );
                    });
                }}
            >
                <div className="grid min-w-64 flex-1 gap-2">
                    <Label htmlFor={`${ids}-licence`}>
                        {t('brokerage.verify.licence')}
                    </Label>
                    <Input
                        id={`${ids}-licence`}
                        inputMode="numeric"
                        autoComplete="off"
                        maxLength={20}
                        required
                        value={licence}
                        onChange={(e) => setLicence(e.target.value)}
                    />
                </div>
                <Button
                    type="submit"
                    disabled={busy || licence.trim().length < 5}
                >
                    {busy && !found
                        ? t('brokerage.verify.checking')
                        : t('brokerage.verify.lookup')}
                </Button>
            </form>

            {found ? (
                <div className="space-y-4">
                    <div className="space-y-1 rounded-reos-md border border-border p-4">
                        <p className="flex items-center gap-2 font-medium">
                            <BadgeCheck aria-hidden className="size-5" />
                            {t('brokerage.verify.found')}: {found.licence.name}
                        </p>
                        <p className="text-base text-muted-foreground">
                            {t('brokerage.verify.status', {
                                status: found.licence.active
                                    ? t('brokerage.verify.active')
                                    : t('brokerage.verify.inactive'),
                            })}
                            {found.licence.expires_on
                                ? ` · ${t('brokerage.verify.expires', { date: day(found.licence.expires_on) })}`
                                : ''}
                        </p>
                        <p className="text-base">
                            {found.name_matches
                                ? t('brokerage.verify.nameOk')
                                : t('brokerage.verify.nameOther', {
                                      name: found.licence.name,
                                  })}
                        </p>
                    </div>

                    <fieldset className="space-y-2">
                        <legend className="font-medium">
                            {t('brokerage.verify.brokerages')}
                        </legend>
                        {found.brokerages.map((b) => (
                            <label
                                key={b.number}
                                className={cn(
                                    'flex min-h-12 cursor-pointer items-start gap-3 rounded-reos-md border p-3',
                                    brokerage === b.number
                                        ? 'border-primary ring-2 ring-primary/30'
                                        : 'border-border',
                                    b.on_reos &&
                                        'cursor-not-allowed opacity-70',
                                )}
                            >
                                <input
                                    type="radio"
                                    name={`${ids}-brokerage`}
                                    className="mt-1 size-5 accent-primary"
                                    checked={brokerage === b.number}
                                    disabled={b.on_reos}
                                    onChange={() => setBrokerage(b.number)}
                                />
                                <span className="space-y-0.5">
                                    <span className="flex items-center gap-2 font-medium">
                                        <Building2
                                            aria-hidden
                                            className="size-5"
                                        />
                                        {b.name} · {b.number}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {b.kind === 'entity'
                                            ? t('brokerage.verify.entity')
                                            : t(
                                                  'brokerage.verify.individual',
                                              )}{' '}
                                        ·{' '}
                                        {b.on_reos
                                            ? t('brokerage.verify.onReos')
                                            : t('brokerage.verify.sponsored', {
                                                  count: b.sponsored,
                                              })}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    <fieldset className="space-y-2">
                        <legend className="font-medium">
                            {t('brokerage.verify.role')}
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {ROLES.map((r) => (
                                <label
                                    key={r}
                                    className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                                >
                                    <input
                                        type="radio"
                                        name={`${ids}-role`}
                                        className="size-5 accent-primary"
                                        checked={role === r}
                                        onChange={() => setRole(r)}
                                    />
                                    {t(`brokerage.verify.roles.${r}`)}
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    {askBroker ? (
                        <p
                            role="status"
                            className="rounded-reos-md bg-muted p-3 text-base"
                        >
                            {askBroker}
                        </p>
                    ) : null}

                    <Button
                        disabled={busy || brokerage === null}
                        onClick={() =>
                            void run(async () => {
                                const confirmed = (
                                    await postJson<{ data: BrokerConfirmed }>(
                                        routes.confirm,
                                        { licence, brokerage, role },
                                    )
                                ).data;
                                if (confirmed.verification === null) {
                                    setAskBroker(
                                        t('brokerage.verify.askBroker', {
                                            brokerage: chosen?.name ?? '',
                                        }),
                                    );
                                    return;
                                }
                                onVerified(confirmed);
                            })
                        }
                    >
                        {t('brokerage.verify.continue')}
                    </Button>
                </div>
            ) : null}
            <InputError message={error ?? undefined} />
        </section>
    );
}
