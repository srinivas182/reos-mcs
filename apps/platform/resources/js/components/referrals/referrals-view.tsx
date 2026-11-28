import { Gift, Handshake, Send, UserRound } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CopyField } from '@/components/copy-field';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import type { InviteSummary, Referral, ReferralRoutes } from './types';

const blank = {
    to_name: '',
    to_email: '',
    to_brokerage: '',
    client_name: '',
    client_email: '',
    client_phone: '',
    kind: 'buyer' as 'buyer' | 'seller',
    area: '',
    note: '',
    fee_percent: '25',
};

/** Empty text is sent as null; the fee as a whole number. */
export function referralBody(form: typeof blank) {
    const text = (s: string) => (s.trim() === '' ? null : s.trim());

    return {
        to_name: form.to_name.trim(),
        to_email: form.to_email.trim(),
        to_brokerage: text(form.to_brokerage),
        client_name: form.client_name.trim(),
        client_email: text(form.client_email),
        client_phone: text(form.client_phone),
        kind: form.kind,
        area: text(form.area),
        note: text(form.note),
        fee_percent:
            form.fee_percent.trim() === ''
                ? null
                : Number.parseInt(form.fee_percent, 10),
        client_agreed: true,
    };
}

/**
 * Referrals between agents (B14.3b): refer a client to an agent in
 * another area, with the fee the brokers agreed (paid broker to broker;
 * REOS takes none), and answer referrals sent to you.
 */
export function ReferralsView({
    referrals: initial,
    routes,
}: {
    referrals: { sent: Referral[]; received: Referral[] };
    routes: ReferralRoutes;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [sent, setSent] = useState(initial.sent);
    const [received, setReceived] = useState(initial.received);
    const [form, setForm] = useState(blank);
    const [agreed, setAgreed] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const day = new Intl.DateTimeFormat(i18n.language, {
        month: 'short',
        day: 'numeric',
    });
    const set = (key: keyof typeof blank, value: string) =>
        setForm((now) => ({ ...now, [key]: value }));
    const replace = (list: Referral[], r: Referral) =>
        list.map((x) => (x.id === r.id ? r : x));

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

    const answer = (url: string, message?: string) =>
        run(async () => {
            const next = (await postJson<{ data: Referral }>(url)).data;
            if (next.direction === 'sent') {
                setSent((now) => replace(now, next));
            } else {
                setReceived((now) => replace(now, next));
            }
            if (message) {
                toast.success(message);
            }
        });

    const field = (
        key: keyof typeof blank,
        label: string,
        props: Record<string, unknown> = {},
    ) => (
        <div className="space-y-2">
            <Label htmlFor={`${ids}-${key}`}>{label}</Label>
            <Input
                id={`${ids}-${key}`}
                value={form[key]}
                onChange={(e) => set(key, e.target.value)}
                {...props}
            />
        </div>
    );

    const item = (r: Referral) => (
        <li key={r.id} className="space-y-2 p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-base font-medium">
                    {r.client.name} · {t(`leads.referrals.kinds.${r.kind}`)}
                    {r.area ? ` · ${r.area}` : ''}
                </p>
                <span className="text-sm text-muted-foreground">
                    {t(`leads.referrals.statuses.${r.status}`)} ·{' '}
                    {day.format(new Date(r.sent_at))}
                </span>
            </div>
            <p className="text-sm text-muted-foreground">
                {r.direction === 'sent'
                    ? t('leads.referrals.toLine', {
                          name: [r.to.name, r.to.broker]
                              .filter(Boolean)
                              .join(', '),
                      })
                    : t('leads.referrals.fromLine', {
                          name: [r.from.name, r.from.broker]
                              .filter(Boolean)
                              .join(', '),
                      })}
                {r.fee_percent !== null
                    ? ` · ${t('leads.referrals.feeLine', { fee: r.fee_percent })}`
                    : ''}
            </p>
            {r.note ? <p className="text-base">{r.note}</p> : null}
            <div className="flex flex-wrap gap-2">
                {r.direction === 'received' && r.status === 'sent' ? (
                    <>
                        <Button
                            disabled={busy}
                            onClick={() =>
                                void answer(
                                    routes.accept(r.id),
                                    t('leads.referrals.acceptedToast'),
                                )
                            }
                        >
                            {t('leads.referrals.accept')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => void answer(routes.decline(r.id))}
                        >
                            {t('leads.referrals.decline')}
                        </Button>
                    </>
                ) : null}
                {r.status === 'accepted' || r.status === 'emailed' ? (
                    <Button
                        variant="ghost"
                        disabled={busy}
                        onClick={() => void answer(routes.close(r.id))}
                    >
                        {t('leads.referrals.close')}
                    </Button>
                ) : null}
                {r.contact ? (
                    <Button variant="outline" asChild>
                        <a href={routes.contact(r.contact)}>
                            {t('leads.referrals.openContact')}
                        </a>
                    </Button>
                ) : null}
            </div>
        </li>
    );

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <form
                aria-labelledby={`${ids}-send`}
                className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    void run(async () => {
                        const made = (
                            await postJson<{ data: Referral }>(
                                routes.create,
                                referralBody(form),
                            )
                        ).data;
                        setSent((now) => [made, ...now]);
                        setForm(blank);
                        setAgreed(false);
                        toast.success(t('leads.referrals.done'));
                    });
                }}
            >
                <h2 id={`${ids}-send`} className="text-lg font-semibold">
                    {t('leads.referrals.send')}
                </h2>
                <div className="grid gap-3 sm:grid-cols-2">
                    {field('to_name', t('leads.referrals.toName'), {
                        required: true,
                        minLength: 2,
                        maxLength: 120,
                    })}
                    {field('to_email', t('leads.referrals.toEmail'), {
                        type: 'email',
                        required: true,
                        maxLength: 160,
                    })}
                    {field('to_brokerage', t('leads.referrals.toBrokerage'), {
                        maxLength: 160,
                    })}
                    {field('client_name', t('leads.referrals.clientName'), {
                        required: true,
                        minLength: 2,
                        maxLength: 120,
                    })}
                    {field('client_email', t('leads.referrals.clientEmail'), {
                        type: 'email',
                        maxLength: 160,
                    })}
                    {field('client_phone', t('leads.referrals.clientPhone'), {
                        type: 'tel',
                        maxLength: 30,
                    })}
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-kind`}>
                            {t('leads.referrals.kind')}
                        </Label>
                        <select
                            id={`${ids}-kind`}
                            className={nativeSelect}
                            value={form.kind}
                            onChange={(e) => set('kind', e.target.value)}
                        >
                            {(['buyer', 'seller'] as const).map((k) => (
                                <option key={k} value={k}>
                                    {t(`leads.referrals.kinds.${k}`)}
                                </option>
                            ))}
                        </select>
                    </div>
                    {field('area', t('leads.referrals.area'), {
                        maxLength: 120,
                        placeholder: t('leads.referrals.areaHint'),
                    })}
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor={`${ids}-fee_percent`}>
                            {t('leads.referrals.fee')}
                        </Label>
                        <Input
                            id={`${ids}-fee_percent`}
                            type="number"
                            min={0}
                            max={50}
                            inputMode="numeric"
                            aria-describedby={`${ids}-fee-hint`}
                            value={form.fee_percent}
                            onChange={(e) => set('fee_percent', e.target.value)}
                        />
                        <p
                            id={`${ids}-fee-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {t('leads.referrals.feeHint')}
                        </p>
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor={`${ids}-note`}>
                            {t('leads.referrals.noteLabel')}
                        </Label>
                        <Textarea
                            id={`${ids}-note`}
                            maxLength={1000}
                            value={form.note}
                            onChange={(e) => set('note', e.target.value)}
                        />
                    </div>
                </div>
                <label
                    htmlFor={`${ids}-agreed`}
                    className="flex min-h-12 items-center gap-3 text-base"
                >
                    <Checkbox
                        id={`${ids}-agreed`}
                        checked={agreed}
                        onCheckedChange={(on) => setAgreed(on === true)}
                    />
                    {t('leads.referrals.agreed')}
                </label>
                <InputError message={error ?? undefined} />
                <Button
                    type="submit"
                    disabled={
                        busy ||
                        !agreed ||
                        form.client_name.trim().length < 2 ||
                        (form.client_email.trim() === '' &&
                            form.client_phone.trim() === '')
                    }
                >
                    <Send aria-hidden />
                    {t('leads.referrals.send')}
                </Button>
            </form>

            <div className="space-y-6">
                {sent.length === 0 && received.length === 0 ? (
                    <EmptyState
                        icon={Handshake}
                        title={t('leads.referrals.none')}
                        description={t('leads.referrals.noneHint')}
                    />
                ) : null}
                {received.length > 0 ? (
                    <section aria-labelledby={`${ids}-received`}>
                        <h2
                            id={`${ids}-received`}
                            className="mb-2 text-lg font-semibold"
                        >
                            {t('leads.referrals.received')}
                        </h2>
                        <ul className="divide-y divide-border rounded-reos-md border border-border">
                            {received.map(item)}
                        </ul>
                    </section>
                ) : null}
                {sent.length > 0 ? (
                    <section aria-labelledby={`${ids}-sent`}>
                        <h2
                            id={`${ids}-sent`}
                            className="mb-2 text-lg font-semibold"
                        >
                            {t('leads.referrals.sent')}
                        </h2>
                        <ul className="divide-y divide-border rounded-reos-md border border-border">
                            {sent.map(item)}
                        </ul>
                    </section>
                ) : null}
            </div>
        </div>
    );
}

/** Invite and earn (B14.3b): the link, and who joined through it. */
export function InviteCard({ invite }: { invite: InviteSummary }) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const points = new Intl.NumberFormat(i18n.language);
    const day = new Intl.DateTimeFormat(i18n.language, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

    return (
        <section
            aria-labelledby={`${ids}-invite`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
        >
            <h2
                id={`${ids}-invite`}
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <Gift aria-hidden className="size-5" />
                {t('leads.invites.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('leads.invites.purpose', {
                    points: points.format(invite.points_each),
                })}
            </p>
            <CopyField label={t('leads.invites.link')} value={invite.link} />
            <p className="text-base">
                {t('leads.invites.codeLine', { code: invite.code })} ·{' '}
                {t('leads.invites.earned', {
                    points: points.format(invite.earned),
                })}
            </p>
            <p className="text-sm text-muted-foreground">
                {t('leads.invites.pointsNote')}
            </p>
            <h3 className="text-base font-semibold">
                {t('leads.invites.invited')}
            </h3>
            {invite.invited.length === 0 ? (
                <EmptyState
                    icon={UserRound}
                    title={t('leads.invites.noneInvited')}
                    description={t('leads.invites.noneHint')}
                />
            ) : (
                <ul className="divide-y divide-border rounded-reos-md border border-border">
                    {invite.invited.map((p) => (
                        <li
                            key={`${p.name}-${p.joined_at}`}
                            className="flex flex-wrap justify-between gap-2 p-3 text-base"
                        >
                            <span>{p.name}</span>
                            <span className="text-muted-foreground">
                                {p.licensed
                                    ? t('leads.invites.licensed')
                                    : t('leads.invites.notYet')}{' '}
                                ·{' '}
                                {t('leads.invites.joined', {
                                    date: day.format(new Date(p.joined_at)),
                                })}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
