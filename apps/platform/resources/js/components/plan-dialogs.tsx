import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    Buyer,
    Interval,
    ProductKind,
    ProductStatus,
} from '@/lib/billing';
import { money } from '@/lib/billing';
import { nativeSelect } from '@/lib/tenants';
import { splits, store, update } from '@/routes/plans';
import { store as storePrice } from '@/routes/plans/prices';

export type PlanPrice = {
    id: number;
    state: string;
    state_name: string;
    amount: number;
    floor: number | null;
    interval: Interval;
    current: boolean;
    starts_at: string;
    ends_at: string | null;
};

export type PlanRow = {
    id: number;
    key: string;
    name: string;
    kind: ProductKind;
    buyer: Buyer;
    description: string | null;
    includes: string[];
    features: string[];
    ai_help: string | null;
    credits: number | null;
    seats: number | null;
    status: ProductStatus;
    /** The school that sells it (B5.2b); null: REOS. */
    seller: { id: number; name: string } | null;
    splits: PlanSplit[];
    prices: PlanPrice[];
};

/** An instructor's or mentor's share of a school product's sales (B5.2b). */
export type PlanSplit = {
    email: string;
    name: string;
    role: 'instructor' | 'mentor';
    percent: number;
};

export type PlanOptions = {
    kinds: ProductKind[];
    buyers: Buyer[];
    intervals: Interval[];
    statuses: ProductStatus[];
    states: { code: string; name: string }[];
    schools: { id: number; name: string }[];
    fee_percent: number;
};

/**
 * New plan and Edit (screen 67): name, kind, buyer, what it includes, the
 * features it unlocks, AI help and credits. Prices are set per state with
 * "Set a price".
 */
export function PlanDialog({
    open,
    onOpenChange,
    plan,
    options,
    defaultBuyer,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plan: PlanRow | null;
    options: PlanOptions;
    defaultBuyer: Buyer;
}) {
    const { t } = useTranslation();
    const form = plan ? update.form(plan.id) : store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {plan
                            ? t('plans.edit', { name: plan.name })
                            : t('plans.new')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('plans.description')}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-name">
                                    {t('plans.fields.name')}
                                </Label>
                                <Input
                                    id="plan-name"
                                    name="name"
                                    defaultValue={plan?.name}
                                    required
                                    maxLength={160}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-kind">
                                        {t('plans.fields.kind')}
                                    </Label>
                                    <select
                                        id="plan-kind"
                                        name="kind"
                                        className={nativeSelect}
                                        defaultValue={plan?.kind ?? 'package'}
                                    >
                                        {options.kinds.map((kind) => (
                                            <option key={kind} value={kind}>
                                                {t(`plans.kinds.${kind}`)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-buyer">
                                        {t('plans.fields.buyer')}
                                    </Label>
                                    <select
                                        id="plan-buyer"
                                        name="buyer"
                                        className={nativeSelect}
                                        defaultValue={
                                            plan?.buyer ?? defaultBuyer
                                        }
                                    >
                                        {options.buyers.map((buyer) => (
                                            <option key={buyer} value={buyer}>
                                                {t(`plans.buyers.${buyer}`)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-seller">
                                    {t('plans.fields.seller')}
                                </Label>
                                <select
                                    id="plan-seller"
                                    name="seller_organisation_id"
                                    className={nativeSelect}
                                    defaultValue={plan?.seller?.id ?? ''}
                                    aria-describedby="plan-seller-help"
                                >
                                    <option value="">
                                        {t('plans.fields.sellerReos')}
                                    </option>
                                    {options.schools.map((school) => (
                                        <option
                                            key={school.id}
                                            value={school.id}
                                        >
                                            {school.name}
                                        </option>
                                    ))}
                                </select>
                                <p
                                    id="plan-seller-help"
                                    className="text-sm text-muted-foreground"
                                >
                                    {t('plans.fields.sellerHelp', {
                                        percent: options.fee_percent,
                                    })}
                                </p>
                                <InputError
                                    message={errors.seller_organisation_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-description">
                                    {t('plans.fields.description')}
                                </Label>
                                <Input
                                    id="plan-description"
                                    name="description"
                                    defaultValue={plan?.description ?? ''}
                                    maxLength={500}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-includes">
                                    {t('plans.fields.includes')}
                                </Label>
                                <Textarea
                                    id="plan-includes"
                                    name="includes"
                                    rows={4}
                                    defaultValue={
                                        plan?.includes.join('\n') ?? ''
                                    }
                                />
                                <InputError
                                    message={
                                        errors.includes ?? errors['includes.0']
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-features">
                                    {t('plans.fields.features')}
                                </Label>
                                <Textarea
                                    id="plan-features"
                                    name="features"
                                    rows={3}
                                    defaultValue={
                                        plan?.features.join('\n') ?? ''
                                    }
                                    className="font-mono"
                                />
                                <InputError
                                    message={
                                        errors.features ?? errors['features.0']
                                    }
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-ai">
                                        {t('plans.fields.ai_help')}
                                    </Label>
                                    <Input
                                        id="plan-ai"
                                        name="ai_help"
                                        defaultValue={plan?.ai_help ?? ''}
                                        maxLength={160}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="plan-credits">
                                        {t('plans.fields.credits')}
                                    </Label>
                                    <Input
                                        id="plan-credits"
                                        name="credits"
                                        type="number"
                                        min={0}
                                        defaultValue={plan?.credits ?? ''}
                                    />
                                </div>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('common.cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {plan ? t('plans.save') : t('plans.create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Set a price (screen 67): state, billing and amount; the current price for that state and billing ends today and stays in the history. */
export function PriceDialog({
    plan,
    onOpenChange,
    options,
}: {
    plan: PlanRow | null;
    onOpenChange: (open: boolean) => void;
    options: PlanOptions;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={plan !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('plans.setPrice')}</DialogTitle>
                    <DialogDescription>{plan?.name}</DialogDescription>
                </DialogHeader>
                {plan ? (
                    <Form
                        {...storePrice.form(plan.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="price-state">
                                            {t('plans.price.state')}
                                        </Label>
                                        <select
                                            id="price-state"
                                            name="state"
                                            className={nativeSelect}
                                            defaultValue="TX"
                                        >
                                            {options.states.map((s) => (
                                                <option
                                                    key={s.code}
                                                    value={s.code}
                                                >
                                                    {s.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="price-interval">
                                            {t('plans.price.interval')}
                                        </Label>
                                        <select
                                            id="price-interval"
                                            name="interval"
                                            className={nativeSelect}
                                            defaultValue="once"
                                        >
                                            {options.intervals.map(
                                                (interval) => (
                                                    <option
                                                        key={interval}
                                                        value={interval}
                                                    >
                                                        {t(
                                                            `plans.intervals.${interval}`,
                                                        )}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="price-amount">
                                        {t('plans.price.amount')}
                                    </Label>
                                    <Input
                                        id="price-amount"
                                        name="amount"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        inputMode="decimal"
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="price-floor">
                                        {t('plans.price.floor')}
                                    </Label>
                                    <Input
                                        id="price-floor"
                                        name="floor"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        inputMode="decimal"
                                    />
                                    <InputError message={errors.floor} />
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        {t('common.cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {t('plans.price.save')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

/** Price history (screen 67): every price each plan has had. */
export function PriceHistoryDialog({
    open,
    onOpenChange,
    plans,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plans: PlanRow[];
}) {
    const { t, i18n } = useTranslation();
    const date = (iso: string) =>
        new Date(iso).toLocaleDateString(i18n.language, {
            dateStyle: 'medium',
        });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('plans.history')}</DialogTitle>
                    <DialogDescription>
                        {t('plans.historyIntro')}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    {plans
                        .filter((plan) => plan.prices.length > 0)
                        .map((plan) => (
                            <section key={plan.id} className="space-y-2">
                                <h3 className="font-semibold">{plan.name}</h3>
                                <ul className="divide-y divide-border rounded-reos-md border border-border">
                                    {plan.prices.map((price) => (
                                        <li
                                            key={price.id}
                                            className="flex flex-wrap justify-between gap-2 px-3 py-2 text-body"
                                        >
                                            <span>
                                                {price.state_name} ·{' '}
                                                {t(
                                                    `plans.intervals.${price.interval}`,
                                                )}{' '}
                                                ·{' '}
                                                {money(
                                                    price.amount,
                                                    i18n.language,
                                                )}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {t('plans.from', {
                                                    date: date(price.starts_at),
                                                })}
                                                {price.ends_at
                                                    ? ` ${t('plans.until', { date: date(price.ends_at) })}`
                                                    : ` · ${t('plans.current')}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

type SplitDraft = { email: string; role: PlanSplit['role']; percent: string };

/**
 * Revenue split (screen 67, B5.2b): instructors' and mentors' shares of a
 * school product's sales, as a percent of what's left after REOS's
 * platform fee. The school keeps the rest.
 */
export function SplitsDialog({
    plan,
    feePercent,
    onOpenChange,
}: {
    plan: PlanRow | null;
    feePercent: number;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={plan !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('plans.splits.title', { name: plan?.name ?? '' })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('plans.splits.help', {
                            percent: feePercent,
                            school: plan?.seller?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>
                {plan ? (
                    <SplitsForm
                        key={plan.id}
                        plan={plan}
                        onDone={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function SplitsForm({ plan, onDone }: { plan: PlanRow; onDone: () => void }) {
    const { t } = useTranslation();
    const [rows, setRows] = useState<SplitDraft[]>(
        plan.splits.map((s) => ({
            email: s.email,
            role: s.role,
            percent: String(s.percent),
        })),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const total = rows.reduce((sum, r) => sum + (Number(r.percent) || 0), 0);
    const change = (index: number, patch: Partial<SplitDraft>) =>
        setRows(rows.map((r, i) => (i === index ? { ...r, ...patch } : r)));

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    splits(plan.id).url,
                    {
                        splits: rows.map((r) => ({
                            ...r,
                            percent: Number(r.percent),
                        })),
                    },
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onError: setErrors,
                        onSuccess: onDone,
                    },
                );
            }}
        >
            {rows.length === 0 ? (
                <p className="text-body text-muted-foreground">
                    {t('plans.splits.none')}
                </p>
            ) : null}
            {rows.map((row, index) => (
                <fieldset
                    key={index}
                    className="grid gap-3 rounded-reos-md border border-border p-3 sm:grid-cols-[2fr_1fr_1fr]"
                >
                    <legend className="sr-only">
                        {t('plans.splits.payee', { number: index + 1 })}
                    </legend>
                    <div className="grid gap-2">
                        <Label htmlFor={`split-email-${index}`}>
                            {t('plans.splits.email')}
                        </Label>
                        <Input
                            id={`split-email-${index}`}
                            type="email"
                            value={row.email}
                            onChange={(e) =>
                                change(index, { email: e.target.value })
                            }
                            required
                        />
                        <InputError message={errors[`splits.${index}.email`]} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`split-role-${index}`}>
                            {t('plans.splits.role')}
                        </Label>
                        <select
                            id={`split-role-${index}`}
                            className={nativeSelect}
                            value={row.role}
                            onChange={(e) =>
                                change(index, {
                                    role: e.target.value as PlanSplit['role'],
                                })
                            }
                        >
                            <option value="instructor">
                                {t('payouts.roles.instructor')}
                            </option>
                            <option value="mentor">
                                {t('payouts.roles.mentor')}
                            </option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`split-percent-${index}`}>
                            {t('plans.splits.percent')}
                        </Label>
                        <Input
                            id={`split-percent-${index}`}
                            type="number"
                            min={1}
                            max={100}
                            value={row.percent}
                            onChange={(e) =>
                                change(index, { percent: e.target.value })
                            }
                            required
                        />
                        <InputError
                            message={errors[`splits.${index}.percent`]}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="sm:col-span-3"
                        onClick={() =>
                            setRows(rows.filter((_, i) => i !== index))
                        }
                    >
                        {t('plans.splits.remove')}
                    </Button>
                </fieldset>
            ))}
            <InputError message={errors.splits} />
            <Button
                type="button"
                variant="outline"
                onClick={() =>
                    setRows([
                        ...rows,
                        { email: '', role: 'instructor', percent: '' },
                    ])
                }
            >
                {t('plans.splits.add')}
            </Button>
            <p className="text-sm text-muted-foreground">
                {t('plans.splits.schoolKeeps', {
                    percent: Math.max(0, 100 - total),
                })}
            </p>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing || total > 100}>
                    {t('plans.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
