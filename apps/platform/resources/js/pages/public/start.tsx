import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Interval } from '@/lib/billing';
import { priceLabel } from '@/lib/billing';
import { cn } from '@/lib/utils';
import { VerifyBrokerage } from '@/components/brokerage/verify-brokerage';
import { show, store } from '@/routes/start';
import { confirm, lookup } from '@/routes/start/verify';

type Plan = {
    id: number;
    product: string;
    description: string | null;
    includes: string[];
    seats: number | null;
    interval: Interval;
    amount: number;
};

/**
 * Start your brokerage or school on REOS (A6.1b, the Sprint 6 gate): its
 * name and states, then a plan for the first state, then payment on the
 * web. The tenant is ready the moment the payment lands, with its owner as
 * broker or school admin.
 */
type Verification = {
    id: string;
    brokerage: string;
    number: string;
    sponsored: number;
};

export default function StartTenant({
    kind,
    verification = null,
    state,
    states,
    plans,
}: {
    kind: 'brokerage' | 'school';
    /** A brokerage starts with its designated broker's TREC check (A15.1a). */
    verification?: Verification | null;
    state: string;
    states: { code: string; name: string }[];
    plans: Plan[];
}) {
    const { t, i18n } = useTranslation();
    const [name, setName] = useState(verification?.brokerage ?? '');
    const [chosenStates, setChosenStates] = useState<string[]>([state]);
    const [plan, setPlan] = useState<number | null>(plans[0]?.id ?? null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={t(`signup.${kind}.title`)} />
            <div className="mx-auto max-w-3xl space-y-8 px-4 py-12 md:px-8">
                <header className="space-y-2">
                    <h1 className="text-title font-semibold">
                        {t(`signup.${kind}.title`)}
                    </h1>
                    <p className="text-body text-muted-foreground">
                        {t(`signup.${kind}.intro`)}
                    </p>
                </header>
                {kind === 'brokerage' && verification === null ? (
                    <VerifyBrokerage
                        routes={{
                            lookup: lookup().url,
                            confirm: confirm().url,
                        }}
                        onVerified={() =>
                            router.reload({ only: ['verification'] })
                        }
                    />
                ) : null}
                {verification ? (
                    <p
                        role="status"
                        className="rounded-reos-md border border-border bg-card p-4 text-base"
                    >
                        {t('brokerage.verify.verified', {
                            name: verification.brokerage,
                            count: verification.sponsored,
                        })}
                    </p>
                ) : null}
                {kind === 'brokerage' && verification === null ? null : (
                    <form
                        className="space-y-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(
                                store(kind).url,
                                {
                                    name,
                                    states: chosenStates,
                                    price_id: plan,
                                    verification: verification?.id ?? null,
                                },
                                {
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                    onError: setErrors,
                                },
                            );
                        }}
                    >
                        <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                            <div className="grid gap-2">
                                <Label htmlFor="org-name">
                                    {t(`signup.${kind}.name`)}
                                </Label>
                                <Input
                                    id="org-name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    maxLength={160}
                                    required
                                    autoComplete="organization"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <fieldset className="grid gap-2">
                                <legend className="font-medium">
                                    {t('signup.states')}
                                </legend>
                                <p className="text-sm text-muted-foreground">
                                    {t('signup.statesHelp')}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {states.map((s) => (
                                        <label
                                            key={s.code}
                                            className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={chosenStates.includes(
                                                    s.code,
                                                )}
                                                disabled={s.code === state}
                                                onChange={(e) =>
                                                    setChosenStates(
                                                        e.target.checked
                                                            ? [
                                                                  ...chosenStates,
                                                                  s.code,
                                                              ]
                                                            : chosenStates.filter(
                                                                  (c) =>
                                                                      c !==
                                                                      s.code,
                                                              ),
                                                    )
                                                }
                                                className="size-5 accent-primary"
                                            />
                                            {s.name}
                                        </label>
                                    ))}
                                </div>
                                <InputError
                                    message={
                                        errors.states ?? errors['states.0']
                                    }
                                />
                            </fieldset>
                        </section>

                        <section aria-labelledby="plans" className="space-y-3">
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <h2 id="plans" className="font-semibold">
                                    {t('signup.plansFor', {
                                        state:
                                            states.find((s) => s.code === state)
                                                ?.name ?? state,
                                    })}
                                </h2>
                                {states.length > 1 ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        {t('signup.priceState')}
                                        <select
                                            className="h-12 rounded-reos-md border border-input bg-background px-3 text-base"
                                            value={state}
                                            onChange={(e) =>
                                                router.get(
                                                    show(kind, {
                                                        query: {
                                                            state: e.target
                                                                .value,
                                                        },
                                                    }).url,
                                                )
                                            }
                                        >
                                            {states.map((s) => (
                                                <option
                                                    key={s.code}
                                                    value={s.code}
                                                >
                                                    {s.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                ) : null}
                            </div>
                            {plans.length === 0 ? (
                                <EmptyState
                                    icon={Check}
                                    title={t('signup.noPlansTitle')}
                                    description={t('signup.noPlans')}
                                />
                            ) : (
                                <div
                                    role="radiogroup"
                                    aria-labelledby="plans"
                                    className="grid gap-3 sm:grid-cols-2"
                                >
                                    {plans.map((p) => (
                                        <label
                                            key={p.id}
                                            className={cn(
                                                'flex cursor-pointer flex-col gap-2 rounded-reos-lg border bg-card p-5',
                                                plan === p.id
                                                    ? 'border-primary ring-2 ring-primary/30'
                                                    : 'border-border',
                                            )}
                                        >
                                            <span className="flex items-center gap-3">
                                                <input
                                                    type="radio"
                                                    name="plan"
                                                    checked={plan === p.id}
                                                    onChange={() =>
                                                        setPlan(p.id)
                                                    }
                                                    className="size-5 accent-primary"
                                                />
                                                <span className="font-semibold">
                                                    {p.product}
                                                </span>
                                            </span>
                                            <span className="text-title font-semibold">
                                                {priceLabel(
                                                    t,
                                                    p.interval,
                                                    p.amount,
                                                    i18n.language,
                                                )}
                                            </span>
                                            {p.seats ? (
                                                <span className="text-sm text-muted-foreground">
                                                    {t('signup.seats', {
                                                        count: p.seats,
                                                    })}
                                                </span>
                                            ) : null}
                                            <ul className="space-y-1">
                                                {p.includes.map((item) => (
                                                    <li
                                                        key={item}
                                                        className="flex gap-2 text-body"
                                                    >
                                                        <Check
                                                            aria-hidden
                                                            className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                                        />
                                                        {item}
                                                    </li>
                                                ))}
                                            </ul>
                                        </label>
                                    ))}
                                </div>
                            )}
                            <InputError
                                message={errors.price_id ?? errors.price}
                            />
                        </section>

                        <Button
                            type="submit"
                            disabled={processing || plan === null}
                            className="w-full sm:w-auto"
                        >
                            {t('signup.continue')}
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            {t('signup.webOnly')}
                        </p>
                        <InputError message={errors.verification} />
                    </form>
                )}
            </div>
        </>
    );
}
