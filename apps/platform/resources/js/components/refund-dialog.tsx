import { router } from '@inertiajs/react';
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
import { money } from '@/lib/billing';
import { cn } from '@/lib/utils';
import { refund } from '@/routes/gateways';

export type RefundableOrder = {
    uuid: string;
    product: string;
    buyer: { name: string; email: string | null } | null;
    gateway: string;
    environment: string;
    total: number | null;
    status: 'paid' | 'refunded';
    paid_at: string | null;
    policy: {
        mode: 'full_and_partial' | 'full_only' | 'none';
        refundable: number;
        days: number;
        window_days: number;
        within_policy: boolean;
        progress_percent?: number;
        max_progress_percent?: number;
    };
};

/**
 * Refund an order (screen 66, B5.3): everything left (default) or part of
 * it, with the reason. Shows the policy check first: a full refund within
 * the window keeps to it; anything else is marked outside the policy.
 * A full refund ends access; a partial one keeps it.
 */
export function RefundDialog({
    order,
    onOpenChange,
}: {
    order: RefundableOrder | null;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={order !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {order ? (
                    <RefundForm
                        key={order.uuid}
                        order={order}
                        onDone={() => onOpenChange(false)}
                    />
                ) : (
                    <DialogTitle className="sr-only">
                        {t('billing.refunds.title', { product: '' })}
                    </DialogTitle>
                )}
            </DialogContent>
        </Dialog>
    );
}

function RefundForm({
    order,
    onDone,
}: {
    order: RefundableOrder;
    onDone: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const partial =
        amount !== '' &&
        Math.round(Number(amount) * 100) < order.policy.refundable;
    const keeps = order.policy.within_policy && !partial;

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.post(
                    refund(order.uuid).url,
                    { amount: amount === '' ? null : amount, reason },
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
            <DialogHeader>
                <DialogTitle>
                    {t('billing.refunds.title', { product: order.product })}
                </DialogTitle>
                <DialogDescription>
                    {t('billing.refunds.help', {
                        amount: money(order.policy.refundable, i18n.language),
                    })}
                </DialogDescription>
            </DialogHeader>
            <p
                className={cn(
                    'rounded-reos-md p-3 text-sm',
                    keeps
                        ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                        : 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
                )}
            >
                {keeps
                    ? t('billing.refunds.within', {
                          count: order.policy.days,
                          window: order.policy.window_days,
                      })
                    : t('billing.refunds.outside', {
                          count: order.policy.days,
                          window: order.policy.window_days,
                      })}{' '}
                {t('billing.refunds.progressNote')}
            </p>
            {order.policy.mode === 'full_only' ? (
                <p className="text-sm text-muted-foreground">
                    {t('billing.refunds.fullOnlyNote')}
                </p>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor="refund-amount">
                        {t('billing.refunds.amount')}
                    </Label>
                    <Input
                        id="refund-amount"
                        type="number"
                        min={0.01}
                        step={0.01}
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        placeholder={(order.policy.refundable / 100).toFixed(2)}
                        aria-describedby="refund-amount-help"
                        className="max-w-48"
                    />
                    <p
                        id="refund-amount-help"
                        className="text-sm text-muted-foreground"
                    >
                        {t('billing.refunds.amountHelp')}
                    </p>
                    <InputError message={errors.amount} />
                </div>
            )}
            {order.policy.mode === 'full_only' ? (
                <InputError message={errors.amount} />
            ) : null}
            <div className="grid gap-2">
                <Label htmlFor="refund-reason">
                    {t('billing.refunds.reason')}
                </Label>
                <Textarea
                    id="refund-reason"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    rows={3}
                    maxLength={300}
                    required
                />
                <InputError message={errors.reason} />
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={processing}
                >
                    {partial
                        ? t('billing.refunds.refundPart')
                        : t('billing.refunds.refundAll')}
                </Button>
            </DialogFooter>
        </form>
    );
}
