import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { nativeSelect, tenantTypeLabel } from '@/lib/tenants';
import { store } from '@/routes/tenants';
import type { TenantOptions, TenantPlan, TenantType } from '@/types';

/** What each type usually starts on. */
const PLAN_FOR: Record<TenantType, TenantPlan> = {
    association: 'association',
    brokerage: 'brokerage',
    school: 'school',
    provider_business: 'partner',
    team: 'inherits',
};

/**
 * New tenant (screen 59): name, type, the brokerage a team belongs to,
 * states, plan, seats and an optional trial.
 */
export function NewTenantDialog({
    open,
    onOpenChange,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: TenantOptions;
}) {
    const { t } = useTranslation();
    const [type, setType] = useState<TenantType | ''>('');
    const [plan, setPlan] = useState<TenantPlan | ''>('');
    const [states, setStates] = useState<string[]>(
        options.states.length === 1 ? [options.states[0]!.code] : [],
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('tenants.new.title')}</DialogTitle>
                    <DialogDescription>
                        {t('tenants.new.intro')}
                    </DialogDescription>
                </DialogHeader>
                <Form {...store.form()} className="space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-name">
                                    {t('tenants.new.name')}
                                </Label>
                                <Input
                                    id="tenant-name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-type">
                                    {t('tenants.new.type')}
                                </Label>
                                <select
                                    id="tenant-type"
                                    name="type"
                                    required
                                    className={nativeSelect}
                                    value={type}
                                    onChange={(e) => {
                                        const next = e.target.value as
                                            | TenantType
                                            | '';
                                        setType(next);
                                        if (next !== '')
                                            setPlan(PLAN_FOR[next]);
                                    }}
                                >
                                    <option value="">
                                        {t('tenants.new.choose')}
                                    </option>
                                    {options.types.map((value) => (
                                        <option key={value} value={value}>
                                            {tenantTypeLabel(t, value)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>
                            {type === 'team' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant-parent">
                                        {t('tenants.new.parent')}
                                    </Label>
                                    <select
                                        id="tenant-parent"
                                        name="parent_id"
                                        required
                                        className={nativeSelect}
                                        defaultValue=""
                                    >
                                        <option value="">
                                            {t('tenants.new.choose')}
                                        </option>
                                        {options.brokerages.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.parent_id} />
                                </div>
                            ) : null}
                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('tenants.new.states')}
                                </legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {options.states.map((state) => (
                                        <label
                                            key={state.code}
                                            className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                                        >
                                            <Checkbox
                                                checked={states.includes(
                                                    state.code,
                                                )}
                                                onCheckedChange={(on) =>
                                                    setStates(
                                                        on
                                                            ? [
                                                                  ...states,
                                                                  state.code,
                                                              ]
                                                            : states.filter(
                                                                  (s) =>
                                                                      s !==
                                                                      state.code,
                                                              ),
                                                    )
                                                }
                                            />
                                            {state.name}
                                        </label>
                                    ))}
                                </div>
                                {states.map((code) => (
                                    <input
                                        key={code}
                                        type="hidden"
                                        name="states[]"
                                        value={code}
                                    />
                                ))}
                                <InputError
                                    message={
                                        errors.states ?? errors['states.0']
                                    }
                                />
                            </fieldset>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant-plan">
                                        {t('tenants.new.plan')}
                                    </Label>
                                    <select
                                        id="tenant-plan"
                                        name="plan"
                                        required
                                        className={nativeSelect}
                                        value={plan}
                                        onChange={(e) =>
                                            setPlan(
                                                e.target.value as
                                                    | TenantPlan
                                                    | '',
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('tenants.new.choose')}
                                        </option>
                                        {options.plans.map((value) => (
                                            <option key={value} value={value}>
                                                {t(`tenants.plans.${value}`)}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.plan} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant-seats">
                                        {t('tenants.new.seats')}
                                    </Label>
                                    <Input
                                        id="tenant-seats"
                                        name="seats"
                                        type="number"
                                        min={1}
                                        inputMode="numeric"
                                        aria-describedby="tenant-seats-hint"
                                    />
                                    <p
                                        id="tenant-seats-hint"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {t('tenants.new.seatsHint')}
                                    </p>
                                    <InputError message={errors.seats} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="tenant-trial">
                                    {t('tenants.new.trial')}
                                </Label>
                                <Input
                                    id="tenant-trial"
                                    name="trial_days"
                                    type="number"
                                    min={1}
                                    max={90}
                                    inputMode="numeric"
                                    className="max-w-40"
                                />
                                <InputError message={errors.trial_days} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('tenants.new.create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
