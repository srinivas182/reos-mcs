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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { GatewayId, ProductKind } from '@/lib/billing';
import { pause, update } from '@/routes/gateways';

export type GatewayHealth =
    | 'healthy'
    | 'simulator'
    | 'failing'
    | 'autoPaused'
    | null;

export type GatewayRow = {
    id: GatewayId;
    name: string;
    mode: 'off' | 'simulator' | 'sandbox' | 'live';
    running: boolean;
    is_default: boolean;
    position: number;
    states: string[] | null;
    used_for: ProductKind[] | null;
    tenant_accounts: boolean;
    paused: boolean;
    paused_reason: string | null;
    capabilities: {
        recurring: boolean;
        tax: boolean;
        payouts: boolean;
        tenant_accounts: boolean;
    };
    health: GatewayHealth;
    orders_today: { paid: number; expired: number };
};

export type GatewayOptions = {
    kinds: ProductKind[];
    states: { code: string; name: string }[];
};

function Checklist<T extends string>({
    legend,
    help,
    values,
    selected,
    label,
    onChange,
}: {
    legend: string;
    help: string;
    values: T[];
    selected: T[];
    label: (value: T) => string;
    onChange: (next: T[]) => void;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="font-medium">{legend}</legend>
            <p className="text-sm text-muted-foreground">{help}</p>
            <div className="grid max-h-56 gap-1 overflow-y-auto sm:grid-cols-2">
                {values.map((value) => (
                    <label
                        key={value}
                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                    >
                        <input
                            type="checkbox"
                            checked={selected.includes(value)}
                            onChange={(e) =>
                                onChange(
                                    e.target.checked
                                        ? [...selected, value]
                                        : selected.filter((v) => v !== value),
                                )
                            }
                            className="size-5 accent-primary"
                        />
                        {label(value)}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/**
 * How one gateway is used (screen 66, B5.2a): the states and kinds of
 * product it serves (none ticked means all), whether it's the default, and
 * whether tenants may connect their own account (Stripe and Authorize.net).
 */
export function GatewayDialog({
    gateway,
    options,
    onOpenChange,
}: {
    gateway: GatewayRow | null;
    options: GatewayOptions;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={gateway !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                {gateway ? (
                    <GatewayForm
                        key={gateway.id}
                        gateway={gateway}
                        options={options}
                        onDone={() => onOpenChange(false)}
                    />
                ) : (
                    <DialogTitle className="sr-only">
                        {t('gateways.title')}
                    </DialogTitle>
                )}
            </DialogContent>
        </Dialog>
    );
}

function GatewayForm({
    gateway,
    options,
    onDone,
}: {
    gateway: GatewayRow;
    options: GatewayOptions;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [states, setStates] = useState<string[]>(gateway.states ?? []);
    const [usedFor, setUsedFor] = useState<ProductKind[]>(
        gateway.used_for ?? [],
    );
    const [isDefault, setIsDefault] = useState(gateway.is_default);
    const [tenantAccounts, setTenantAccounts] = useState(
        gateway.tenant_accounts,
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const names = Object.fromEntries(
        options.states.map((s) => [s.code, s.name]),
    );

    const save = () =>
        router.put(
            update(gateway.id).url,
            {
                states,
                used_for: usedFor,
                is_default: isDefault,
                tenant_accounts: tenantAccounts,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onDone,
            },
        );

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
            className="space-y-5"
        >
            <DialogHeader>
                <DialogTitle>
                    {t('gateways.edit', { name: gateway.name })}
                </DialogTitle>
                <DialogDescription>{t('gateways.editHelp')}</DialogDescription>
            </DialogHeader>
            <Checklist
                legend={t('gateways.fields.states')}
                help={t('gateways.fields.statesHelp')}
                values={options.states.map((s) => s.code)}
                selected={states}
                label={(code) => names[code] ?? code}
                onChange={setStates}
            />
            <InputError message={errors.states ?? errors['states.0']} />
            <Checklist
                legend={t('gateways.fields.usedFor')}
                help={
                    gateway.capabilities.recurring
                        ? t('gateways.fields.usedForHelp')
                        : t('gateways.fields.usedForOnce')
                }
                values={options.kinds}
                selected={usedFor}
                label={(kind) => t(`plans.kinds.${kind}`)}
                onChange={setUsedFor}
            />
            <InputError message={errors.used_for ?? errors['used_for.0']} />
            <label className="flex min-h-12 items-center gap-3">
                <input
                    type="checkbox"
                    checked={isDefault}
                    onChange={(e) => setIsDefault(e.target.checked)}
                    className="size-5 accent-primary"
                />
                <span>
                    <span className="block font-medium">
                        {t('gateways.fields.default')}
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        {t('gateways.fields.defaultHelp')}
                    </span>
                </span>
            </label>
            <label className="flex min-h-12 items-center gap-3">
                <input
                    type="checkbox"
                    checked={tenantAccounts}
                    disabled={!gateway.capabilities.tenant_accounts}
                    onChange={(e) => setTenantAccounts(e.target.checked)}
                    className="size-5 accent-primary"
                />
                <span>
                    <span className="block font-medium">
                        {t('gateways.fields.tenantAccounts')}
                    </span>
                    <span className="block text-sm text-muted-foreground">
                        {gateway.capabilities.tenant_accounts
                            ? t('gateways.fields.tenantAccountsHelp')
                            : t('gateways.fields.tenantAccountsNo')}
                    </span>
                </span>
            </label>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('gateways.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}

/** The kill switch, with a reason kept in the audit log. */
export function PauseGatewayDialog({
    gateway,
    onOpenChange,
}: {
    gateway: GatewayRow | null;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog
            open={gateway !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setReason('');
                    setError(undefined);
                }

                onOpenChange(open);
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('gateways.pauseTitle', {
                            name: gateway?.name ?? '',
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('gateways.pauseHelp')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        if (gateway === null) {
                            return;
                        }

                        router.post(
                            pause(gateway.id).url,
                            { reason },
                            {
                                preserveScroll: true,
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                                onError: (errors) => setError(errors.reason),
                                onSuccess: () => {
                                    setReason('');
                                    onOpenChange(false);
                                },
                            },
                        );
                    }}
                    className="space-y-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="pause-reason">
                            {t('gateways.pauseReason')}
                        </Label>
                        <Textarea
                            id="pause-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            maxLength={200}
                            required
                        />
                        <InputError message={error} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {t('gateways.pause')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
