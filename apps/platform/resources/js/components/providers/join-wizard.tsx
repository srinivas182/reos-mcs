import { CheckCircle2, Circle, MapPin, ShieldCheck } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/billing';
import { deleteJson, getJson, postForm, postJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { CategoryStep } from './category-step';
import type {
    AreaRow,
    CategoryCard,
    Onboarding,
    OnboardingRoutes,
    StepKey,
    Tier,
} from './types';

const card = 'space-y-4 rounded-reos-lg border border-border bg-card p-5';
const select =
    'h-12 rounded-reos-md border border-input bg-background px-3 text-base';

function errorsOf(e: unknown): Record<string, string> {
    if (typeof e === 'object' && e !== null && 'errors' in e) {
        const errors = (e as { errors: Record<string, string[] | string> })
            .errors;

        return Object.fromEntries(
            Object.entries(errors).map(([k, v]) => [
                k,
                Array.isArray(v) ? (v[0] ?? '') : v,
            ]),
        );
    }

    return { form: e instanceof Error ? e.message : String(e) };
}

const STATUSES = ['joining', 'waiting', 'live', 'hidden'] as const;
const HIDDEN = [
    'licence_expired',
    'licence_inactive',
    'licence_rejected',
    'licence_review',
    'licence_missing',
] as const;
const KINDS = ['trec_inspector', 'tdi', 'nmls', 'business_id'] as const;
const LICENCE_STATUSES = [
    'verified',
    'inactive',
    'review',
    'rejected',
] as const;
const RULES = [
    'flat_fee',
    'no_payments',
    'consent',
    'advertising',
    'no_spam',
] as const;

/** A value from the server narrowed to the keys this page can show. */
function pick<T extends string>(
    list: readonly T[],
    value: string | null,
): T | null {
    return list.find((v) => v === value) ?? null;
}

/** The first step not done yet, or the last. */
const firstOpen = (o: Onboarding): StepKey =>
    o.steps.find((s) => !s.done)?.key ?? 'rules';

/**
 * Joining the provider network (B15.2, provider screens 1–6): welcome,
 * category, licence or business details, service areas, plan on reos.com,
 * and the network rules. The profile goes live when every step is done and
 * the licence is verified.
 */
export function JoinWizard({
    categories,
    onboarding: initial,
    routes,
    onCheckout = (url) => window.location.assign(url),
}: {
    categories: CategoryCard[];
    onboarding: Onboarding;
    routes: OnboardingRoutes;
    onCheckout?: (url: string) => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [o, setO] = useState(initial);
    const [step, setStep] = useState<StepKey>(firstOpen(initial));
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const run = async (
        work: () => Promise<{ data: Onboarding }>,
        advance = true,
    ) => {
        setBusy(true);
        setErrors({});

        try {
            const next = (await work()).data;
            setO(next);

            if (advance) {
                setStep(firstOpen(next));
            }
        } catch (e) {
            setErrors(errorsOf(e));
        } finally {
            setBusy(false);
        }
    };

    const hidden = pick(HIDDEN, o.hidden_reason);
    const reason = hidden ? t(`providers.onboarding.hidden.${hidden}`) : '';
    const status = pick(STATUSES, o.status) ?? 'joining';

    return (
        <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <nav
                aria-label={t('providers.onboarding.title')}
                className="space-y-3"
            >
                <p className="text-base text-muted-foreground">
                    {t('providers.onboarding.progress', {
                        done: o.done,
                        total: o.steps.length,
                    })}
                </p>
                <ol className="space-y-1">
                    {o.steps.map((s) => (
                        <li key={s.key}>
                            <button
                                type="button"
                                onClick={() => setStep(s.key)}
                                aria-current={
                                    step === s.key ? 'step' : undefined
                                }
                                className={cn(
                                    'flex min-h-12 w-full items-center gap-3 rounded-reos-md px-3 text-left text-base',
                                    step === s.key
                                        ? 'bg-muted font-medium'
                                        : '',
                                )}
                            >
                                {s.done ? (
                                    <CheckCircle2
                                        aria-hidden
                                        className="size-5 text-reos-tone-success-fg"
                                    />
                                ) : (
                                    <Circle
                                        aria-hidden
                                        className="size-5 text-muted-foreground"
                                    />
                                )}
                                {t(`providers.onboarding.steps.${s.key}`)}
                            </button>
                        </li>
                    ))}
                </ol>
            </nav>

            <div className="space-y-4">
                <p
                    role="status"
                    className={cn(
                        'rounded-reos-md border p-4 text-base',
                        o.status === 'live'
                            ? 'border-reos-tone-success-fg/40 bg-reos-tone-success-bg text-reos-tone-success-fg'
                            : o.status === 'hidden'
                              ? 'border-reos-tone-danger-fg/40 bg-reos-tone-danger-bg text-reos-tone-danger-fg'
                              : 'border-border bg-card',
                    )}
                >
                    {t(`providers.onboarding.status.${status}`, { reason })}
                </p>
                <InputError message={errors.form} />

                {step === 'welcome' ? (
                    <section
                        aria-labelledby={`${ids}-welcome`}
                        className={card}
                    >
                        <h2
                            id={`${ids}-welcome`}
                            className="text-title font-semibold"
                        >
                            {t('providers.onboarding.welcome.title')}
                        </h2>
                        <p className="text-base">
                            {t('providers.onboarding.welcome.body')}
                        </p>
                        <ul className="space-y-2">
                            {(['verified', 'areas', 'flat'] as const).map(
                                (p) => (
                                    <li
                                        key={p}
                                        className="flex gap-2 text-base"
                                    >
                                        <CheckCircle2
                                            aria-hidden
                                            className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                        />
                                        {t(
                                            `providers.onboarding.welcome.points.${p}`,
                                        )}
                                    </li>
                                ),
                            )}
                        </ul>
                        <Button
                            type="button"
                            disabled={busy}
                            onClick={() =>
                                void run(() => postJson(routes.welcome))
                            }
                        >
                            {t('providers.onboarding.welcome.start')}
                        </Button>
                    </section>
                ) : null}

                {step === 'category' ? (
                    <CategoryStep
                        categories={categories}
                        joining={o.joining}
                        routes={routes}
                        onChanged={() =>
                            void run(() => getJson(routes.status), false)
                        }
                    />
                ) : null}
                {step === 'licence' ? (
                    <LicenceStep
                        o={o}
                        routes={routes}
                        busy={busy}
                        errors={errors}
                        run={run}
                    />
                ) : null}
                {step === 'areas' ? (
                    <AreasStep
                        o={o}
                        routes={routes}
                        busy={busy}
                        errors={errors}
                        run={run}
                    />
                ) : null}

                {step === 'plan' ? (
                    <section aria-labelledby={`${ids}-plan`} className={card}>
                        <h2 id={`${ids}-plan`} className="font-semibold">
                            {t('providers.onboarding.plan.title')}
                        </h2>
                        <p className="text-base text-muted-foreground">
                            {t('providers.onboarding.plan.hint')}
                        </p>
                        <PlanStep
                            o={o}
                            busy={busy}
                            error={errors.tier}
                            onBuy={(tier) => {
                                setBusy(true);
                                setErrors({});
                                postJson<{ data: { checkout_url: string } }>(
                                    routes.plan,
                                    { tier },
                                )
                                    .then((res) =>
                                        onCheckout(res.data.checkout_url),
                                    )
                                    .catch((e: unknown) =>
                                        setErrors(errorsOf(e)),
                                    )
                                    .finally(() => setBusy(false));
                            }}
                        />
                    </section>
                ) : null}

                {step === 'rules' ? (
                    <RulesStep
                        o={o}
                        busy={busy}
                        errors={errors}
                        onAccept={() =>
                            void run(() =>
                                postJson(routes.rules, {
                                    version: o.rules.version,
                                    accept: true,
                                }),
                            )
                        }
                    />
                ) : null}
            </div>
        </div>
    );
}

type StepProps = {
    o: Onboarding;
    routes: OnboardingRoutes;
    busy: boolean;
    errors: Record<string, string>;
    run: (
        work: () => Promise<{ data: Onboarding }>,
        advance?: boolean,
    ) => Promise<void>;
};

/** Provider screen 3: the category's licence, or business details and a photo ID. */
function LicenceStep({ o, routes, busy, errors, run }: StepProps) {
    const { t } = useTranslation();
    const ids = useId();
    const [number, setNumber] = useState('');
    const [name, setName] = useState(o.licence.business?.name ?? '');
    const [address, setAddress] = useState(o.licence.business?.address ?? '');
    const [file, setFile] = useState<File | null>(null);
    const kind = o.licence.kind;
    const current = o.licence.licences.find(
        (l) => l.kind === (kind === 'business' ? 'business_id' : kind),
    );

    if (o.joining.category === null) {
        return (
            <EmptyState
                icon={ShieldCheck}
                title={t('providers.onboarding.licence.title')}
                description={t('providers.join.errors.chooseFirst')}
            />
        );
    }

    return (
        <section aria-labelledby={`${ids}-licence`} className={card}>
            <h2 id={`${ids}-licence`} className="font-semibold">
                {kind === 'business'
                    ? t('providers.onboarding.business.title')
                    : t('providers.onboarding.licence.title')}
            </h2>
            {current ? (
                <p className="flex items-center gap-2 text-base">
                    <ShieldCheck aria-hidden className="size-5" />
                    {t(
                        `providers.licences.kinds.${pick(KINDS, current.kind) ?? 'business_id'}`,
                    )}{' '}
                    ·{' '}
                    {t(
                        `providers.licences.statuses.${pick(LICENCE_STATUSES, current.status) ?? 'review'}`,
                    )}
                </p>
            ) : null}
            {kind === 'business' ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const body = new FormData();
                        body.append('name', name);
                        body.append('address', address);

                        if (file) {
                            body.append('id_photo', file);
                        }

                        void run(() => postForm(routes.business, body));
                    }}
                >
                    <p className="text-base text-muted-foreground">
                        {t('providers.onboarding.business.hint')}
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-name`}>
                            {t('providers.onboarding.business.name')}
                        </Label>
                        <Input
                            id={`${ids}-name`}
                            value={name}
                            maxLength={160}
                            required
                            onChange={(e) => setName(e.target.value)}
                            autoComplete="organization"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-address`}>
                            {t('providers.onboarding.business.address')}
                        </Label>
                        <Input
                            id={`${ids}-address`}
                            value={address}
                            maxLength={240}
                            required
                            onChange={(e) => setAddress(e.target.value)}
                            autoComplete="street-address"
                        />
                        <InputError message={errors.address} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-id`}>
                            {t('providers.onboarding.business.id')}
                        </Label>
                        <Input
                            id={`${ids}-id`}
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            aria-describedby={`${ids}-id-hint`}
                            onChange={(e) =>
                                setFile(e.target.files?.[0] ?? null)
                            }
                        />
                        <p
                            id={`${ids}-id-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {current
                                ? t('providers.onboarding.business.idOnFile')
                                : t('providers.onboarding.business.idHint')}
                        </p>
                        <InputError message={errors.id_photo} />
                    </div>
                    <Button type="submit" disabled={busy}>
                        {t('providers.onboarding.business.save')}
                    </Button>
                </form>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void run(async () => {
                            await postJson(routes.licence, { kind, number });

                            return getJson<{ data: Onboarding }>(routes.status);
                        });
                    }}
                >
                    <p className="text-base text-muted-foreground">
                        {t(
                            `providers.onboarding.licence.${kind === 'nmls' || kind === 'tdi' ? kind : 'trec_inspector'}`,
                        )}
                    </p>
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-number`}>
                            {t('providers.onboarding.licence.number')}
                        </Label>
                        <Input
                            id={`${ids}-number`}
                            value={number}
                            maxLength={40}
                            required
                            minLength={3}
                            onChange={(e) => setNumber(e.target.value)}
                        />
                        <InputError message={errors.number ?? errors.kind} />
                    </div>
                    <Button type="submit" disabled={busy}>
                        {t('providers.onboarding.licence.check')}
                    </Button>
                </form>
            )}
        </section>
    );
}

/** Provider screen 4: cities, ZIP codes and radiuses, with agents and providers in each. */
function AreasStep({ o, routes, busy, errors, run }: StepProps) {
    const { t } = useTranslation();
    const ids = useId();
    const [kind, setKind] = useState<AreaRow['kind']>('city');
    const [city, setCity] = useState('');
    const [zip, setZip] = useState('');
    const [miles, setMiles] = useState('10');
    const label = (a: AreaRow) =>
        a.kind === 'city'
            ? (a.city ?? '')
            : a.kind === 'zip'
              ? (a.postal_code ?? '')
              : t('providers.onboarding.areas.radiusLabel', {
                    miles: a.radius_miles,
                    zip: a.postal_code,
                });

    return (
        <section aria-labelledby={`${ids}-areas`} className={card}>
            <h2 id={`${ids}-areas`} className="font-semibold">
                {t('providers.onboarding.areas.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('providers.onboarding.areas.hint')}
            </p>
            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    void run(
                        () =>
                            postJson(routes.areas, {
                                kind,
                                city: kind === 'city' ? city : null,
                                postal_code: kind === 'city' ? null : zip,
                                radius_miles:
                                    kind === 'radius' ? Number(miles) : null,
                            }),
                        false,
                    ).then(() => {
                        setCity('');
                        setZip('');
                    });
                }}
            >
                <div className="grid gap-2">
                    <Label htmlFor={`${ids}-kind`}>
                        {t('providers.onboarding.areas.kind')}
                    </Label>
                    <select
                        id={`${ids}-kind`}
                        className={select}
                        value={kind}
                        onChange={(e) =>
                            setKind(e.target.value as AreaRow['kind'])
                        }
                    >
                        {(['city', 'zip', 'radius'] as const).map((k) => (
                            <option key={k} value={k}>
                                {t(`providers.onboarding.areas.kinds.${k}`)}
                            </option>
                        ))}
                    </select>
                </div>
                {kind === 'city' ? (
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-city`}>
                            {t('providers.onboarding.areas.city')}
                        </Label>
                        <Input
                            id={`${ids}-city`}
                            value={city}
                            maxLength={80}
                            onChange={(e) => setCity(e.target.value)}
                            autoComplete="address-level2"
                        />
                    </div>
                ) : (
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-zip`}>
                            {t('providers.onboarding.areas.zip')}
                        </Label>
                        <Input
                            id={`${ids}-zip`}
                            value={zip}
                            inputMode="numeric"
                            maxLength={5}
                            className="w-32"
                            onChange={(e) => setZip(e.target.value)}
                            autoComplete="postal-code"
                        />
                    </div>
                )}
                {kind === 'radius' ? (
                    <div className="grid gap-2">
                        <Label htmlFor={`${ids}-miles`}>
                            {t('providers.onboarding.areas.radius')}
                        </Label>
                        <Input
                            id={`${ids}-miles`}
                            type="number"
                            min={1}
                            max={50}
                            value={miles}
                            className="w-24"
                            onChange={(e) => setMiles(e.target.value)}
                        />
                    </div>
                ) : null}
                <Button type="submit" variant="outline" disabled={busy}>
                    {t('providers.onboarding.areas.add')}
                </Button>
                <InputError
                    className="w-full"
                    message={errors.city ?? errors.postal_code ?? errors.area}
                />
            </form>
            {o.areas.length === 0 ? (
                <EmptyState
                    icon={MapPin}
                    title={t('providers.onboarding.areas.emptyTitle')}
                    description={t('providers.onboarding.areas.empty')}
                />
            ) : (
                <ul className="divide-y divide-border">
                    {o.areas.map((a) => (
                        <li
                            key={a.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <span className="space-y-1">
                                <span className="block font-medium">
                                    {label(a)}
                                </span>
                                <span className="block text-base text-muted-foreground">
                                    {t('providers.onboarding.areas.stats', {
                                        agents: a.agents,
                                        providers: a.providers,
                                    })}
                                </span>
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                aria-label={t(
                                    'providers.onboarding.areas.remove',
                                    { area: label(a) },
                                )}
                                onClick={() =>
                                    void run(
                                        () => deleteJson(routes.area(a.id)),
                                        false,
                                    ).then(() =>
                                        undoToast({
                                            message: t(
                                                'providers.onboarding.areas.removed',
                                                { area: label(a) },
                                            ),
                                            onUndo: () =>
                                                void run(
                                                    () =>
                                                        postJson(routes.areas, {
                                                            kind: a.kind,
                                                            city: a.city,
                                                            postal_code:
                                                                a.postal_code,
                                                            radius_miles:
                                                                a.radius_miles,
                                                        }),
                                                    false,
                                                ),
                                        }),
                                    )
                                }
                            >
                                {t('providers.onboarding.areas.remove', {
                                    area: '',
                                }).trim()}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/** Provider screen 5: Listed, Pro or Partner at the admin's flat monthly price. */
function PlanStep({
    o,
    busy,
    error,
    onBuy,
}: {
    o: Onboarding;
    busy: boolean;
    error?: string;
    onBuy: (tier: Tier) => void;
}) {
    const { t, i18n } = useTranslation();
    const first: Tier | null =
        o.plans.find((p) => p.tier === o.tier)?.tier ??
        o.plans.find((p) => p.areas >= o.areas.length)?.tier ??
        null;
    const [tier, setTier] = useState<Tier | null>(first);
    const names: Record<Tier, string> = {
        basic: t('providers.onboarding.plan.names.basic'),
        pro: t('providers.onboarding.plan.names.pro'),
        premium: t('providers.onboarding.plan.names.premium'),
    };
    const cards: {
        tier: Tier;
        name: string;
        price: string;
        areas: string;
        current: boolean;
    }[] = o.plans.map((p) => ({
        tier: p.tier,
        name: names[p.tier],
        price: t('providers.onboarding.plan.monthly', {
            price: money(p.monthly, i18n.language),
        }),
        areas: t('providers.onboarding.plan.areas', { count: p.areas }),
        current: o.tier === p.tier,
    }));
    const title = t('providers.onboarding.plan.title');
    const currentLabel = t('providers.onboarding.plan.current');

    if (cards.length === 0) {
        return (
            <p className="text-base">{t('providers.onboarding.plan.none')}</p>
        );
    }

    return (
        <div className="space-y-4">
            <div
                role="radiogroup"
                aria-label={title}
                className="grid gap-3 sm:grid-cols-3"
            >
                {cards.map((c) => (
                    <label
                        key={c.tier}
                        className={cn(
                            'flex cursor-pointer flex-col gap-2 rounded-reos-lg border bg-card p-4',
                            tier === c.tier
                                ? 'border-primary ring-2 ring-primary/30'
                                : 'border-border',
                        )}
                    >
                        <span className="flex items-center gap-3">
                            <input
                                type="radio"
                                name="tier"
                                className="size-5 accent-primary"
                                checked={tier === c.tier}
                                onChange={() => setTier(c.tier)}
                            />
                            <span className="font-semibold">{c.name}</span>
                        </span>
                        <span className="text-title font-semibold">
                            {c.price}
                        </span>
                        <span className="text-base text-muted-foreground">
                            {c.areas}
                        </span>
                        {c.current ? (
                            <span className="text-base font-medium">
                                {currentLabel}
                            </span>
                        ) : null}
                    </label>
                ))}
            </div>
            <InputError message={error} />
            {o.tier === null ? (
                <Button
                    type="button"
                    disabled={busy || tier === null}
                    onClick={() => tier && onBuy(tier)}
                >
                    {t('providers.onboarding.plan.buy')}
                </Button>
            ) : null}
        </div>
    );
}

/** Provider screen 6: the five network rules, accepted with the date and version. */
function RulesStep({
    o,
    busy,
    errors,
    onAccept,
}: {
    o: Onboarding;
    busy: boolean;
    errors: Record<string, string>;
    onAccept: () => void;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [agreed, setAgreed] = useState(false);

    return (
        <section aria-labelledby={`${ids}-rules`} className={card}>
            <h2 id={`${ids}-rules`} className="font-semibold">
                {t('providers.onboarding.rules.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('providers.onboarding.rules.hint')}
            </p>
            <ol className="list-decimal space-y-2 pl-6 text-base">
                {o.rules.keys.flatMap((k) => {
                    const rule = pick(RULES, k);

                    return rule
                        ? [
                              <li key={rule}>
                                  {t(
                                      `providers.onboarding.rules.items.${rule}`,
                                  )}
                              </li>,
                          ]
                        : [];
                })}
            </ol>
            {o.rules.current && o.rules.accepted_at ? (
                <p className="text-base font-medium">
                    {t('providers.onboarding.rules.accepted', {
                        date: new Date(o.rules.accepted_at).toLocaleDateString(
                            i18n.language,
                        ),
                    })}
                </p>
            ) : (
                <>
                    <label className="flex min-h-12 items-center gap-3 text-base">
                        <input
                            type="checkbox"
                            className="size-5 accent-primary"
                            checked={agreed}
                            onChange={(e) => setAgreed(e.target.checked)}
                        />
                        {t('providers.onboarding.rules.accept')}
                    </label>
                    <InputError message={errors.version ?? errors.accept} />
                    <Button
                        type="button"
                        disabled={busy || !agreed}
                        onClick={onAccept}
                    >
                        {t('providers.onboarding.rules.save')}
                    </Button>
                </>
            )}
        </section>
    );
}
