import { Form } from '@inertiajs/react';
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
import { store, update } from '@/routes/plans';
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
    prices: PlanPrice[];
};

export type PlanOptions = {
    kinds: ProductKind[];
    buyers: Buyer[];
    intervals: Interval[];
    statuses: ProductStatus[];
    states: { code: string; name: string }[];
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
