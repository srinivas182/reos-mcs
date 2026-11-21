import type { TFunction } from 'i18next';
import type { TenantRow } from '@/types';

/** A native select at the 48 px control height (admin filters and forms). */
export const nativeSelect =
    'h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50';

export function tenantTypeLabel(t: TFunction, type: string): string {
    return t(`tenants.types.${type as TenantRow['type']}`);
}

/** "Brokerage · 142 seats", "Trial · 14 days left", "Inherits". */
export function planLabel(
    t: TFunction,
    tenant: Pick<TenantRow, 'plan' | 'seats' | 'status' | 'trial_days_left'>,
): string {
    if (tenant.status === 'trial' && tenant.trial_days_left !== null) {
        return t('tenants.trialLeft', { count: tenant.trial_days_left });
    }

    const plan = t(`tenants.plans.${tenant.plan}`);

    return tenant.seats === null
        ? plan
        : t('tenants.planSeats', { plan, count: tenant.seats });
}
